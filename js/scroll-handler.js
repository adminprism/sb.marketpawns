/**
 * ScrollHandler - модуль для унификации поведения полосы прокрутки на всех браузерах
 */
(function() {
    // Объект ScrollHandler с методами для работы с полосой прокрутки
    const ScrollHandler = {
        scrollBar: null,
        initialized: false,
        
        /**
         * Инициализация модуля
         */
        init: function() {
            if (this.initialized) return;
            
            // Получаем полосу прокрутки
            this.scrollBar = document.getElementById("scroll-bar");
            if (!this.scrollBar) {
                console.error("Элемент scroll-bar не найден в DOM.");
                return;
            }
            
            // Обернем полосу прокрутки контейнером, если это еще не сделано
            this.wrapScrollbarInContainer();
            
            // Инициализация обработчиков касаний для мобильных устройств
            this.initTouchHandlers();
            
            // Оптимизация для дисплеев высокой четкости
            this.optimizeForHighDPI();
            
            this.initialized = true;
            console.log("ScrollHandler: инициализирован");
        },
        
        /**
         * Оборачивает полосу прокрутки в контейнер, если это не сделано
         */
        wrapScrollbarInContainer: function() {
            if (this.scrollBar.parentNode.classList.contains('scroll-bar-container')) {
                return; // Уже обернут
            }
            
            const container = document.createElement('div');
            container.className = 'scroll-bar-container';
            
            // Перемещаем полосу прокрутки в контейнер
            this.scrollBar.parentNode.insertBefore(container, this.scrollBar);
            container.appendChild(this.scrollBar);
        },
        
        /**
         * Инициализация обработчиков касаний для сенсорных устройств
         */
        initTouchHandlers: function() {
            this.scrollBar.addEventListener('touchstart', function(e) {
                // Предотвращаем прокрутку страницы при использовании ползунка
                e.preventDefault();
            });
            
            this.scrollBar.addEventListener('touchmove', function(e) {
                // Обработка движения на сенсорных экранах
                const touch = e.touches[0];
                const boundingRect = this.getBoundingClientRect();
                const percentage = (touch.clientX - boundingRect.left) / boundingRect.width;
                
                // Устанавливаем значение в пределах от 0 до максимального
                const newValue = Math.max(0, Math.min(this.max, Math.round(percentage * this.max)));
                
                // Только если значение изменилось
                if (this.value != newValue) {
                    this.value = newValue;
                    // Вызываем обработчик события input
                    const event = new Event('input', { bubbles: true });
                    this.dispatchEvent(event);
                }
            });
        },
        
        /**
         * Оптимизация внешнего вида для устройств с высоким DPI
         */
        optimizeForHighDPI: function() {
            // Определяем плотность пикселей устройства
            const dpr = window.devicePixelRatio || 1;
            
            // Если плотность пикселей больше 1, модифицируем стили
            if (dpr > 1) {
                const thumbSize = Math.round(18 * dpr);
                const trackHeight = Math.round(8 * dpr);
                
                // Применяем стили для устройств с высокой плотностью пикселей
                const styleElement = document.createElement('style');
                styleElement.textContent = `
                    @media (-webkit-min-device-pixel-ratio: ${dpr}), 
                           (min-resolution: ${dpr * 96}dpi) {
                        #scroll-bar::-webkit-slider-thumb {
                            width: ${thumbSize}px;
                            height: ${thumbSize}px;
                            margin-top: -${(thumbSize - trackHeight) / 2}px;
                        }
                        #scroll-bar::-moz-range-thumb {
                            width: ${thumbSize}px;
                            height: ${thumbSize}px;
                        }
                        #scroll-bar::-ms-thumb {
                            width: ${thumbSize}px;
                            height: ${thumbSize}px;
                        }
                    }
                `;
                document.head.appendChild(styleElement);
            }
        },
        
        /**
         * Обновление параметров полосы прокрутки
         * @param {Object} params - объект с параметрами
         * @param {Number} params.curBar - текущий бар
         * @param {Number} params.totalBars - общее количество баров
         * @param {Number} params.visibleBars - количество видимых баров
         */
        update: function(params) {
            if (!this.scrollBar || !params) return;
            
            const { curBar, totalBars, visibleBars } = params;
            
            // Проверка на необходимость полосы прокрутки
            if (totalBars <= visibleBars) {
                // Скрываем полосу прокрутки, если все бары видны
                const container = this.scrollBar.closest('.scroll-bar-container');
                if (container) container.style.display = 'none';
                return;
            } else {
                // Показываем полосу прокрутки
                const container = this.scrollBar.closest('.scroll-bar-container');
                if (container) container.style.display = 'block';
            }
            
            // Рассчитываем максимальное значение для scroll-bar
            const maxScroll = totalBars - visibleBars;
            
            // Временно отключаем анимацию при изменении максимального значения
            if (parseInt(this.scrollBar.max) !== maxScroll) {
                this.scrollBar.style.transition = 'none';
                this.scrollBar.min = 0;
                this.scrollBar.max = maxScroll;
                
                // Возвращаем анимацию после короткой задержки
                setTimeout(() => {
                    this.scrollBar.style.transition = 'value 0.3s ease';
                }, 50);
            }
            
            // Рассчитываем значение для scroll-bar
            const scrollValue = Math.max(0, Math.min(curBar - (visibleBars - 1), maxScroll));
            
            // Устанавливаем значение scroll-bar
            this.scrollBar.value = scrollValue;
        }
    };
    
    // Инициализация ScrollHandler при загрузке DOM
    document.addEventListener('DOMContentLoaded', function() {
        ScrollHandler.init();
    });
    
    // Делаем ScrollHandler доступным в глобальной области видимости
    window.ScrollHandler = ScrollHandler;
})(); 