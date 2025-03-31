// Оптимизация скроллинга для мобильных устройств
document.addEventListener('DOMContentLoaded', function() {
    // Функция для оптимизации обработки скролла
    function optimizeScroll(element) {
        let ticking = false;
        let lastScrollTop = 0;
        let scrollTimeout;

        element.addEventListener('scroll', function() {
            // Отменяем предыдущий таймаут
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }

            // Используем requestAnimationFrame для оптимизации производительности
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    // Получаем текущую позицию скролла
                    const currentScrollTop = element.scrollTop;
                    
                    // Вычисляем разницу
                    const scrollDiff = Math.abs(currentScrollTop - lastScrollTop);
                    
                    // Обновляем последнюю позицию
                    lastScrollTop = currentScrollTop;
                    
                    // Если разница значительная, обновляем UI
                    if (scrollDiff > 5) {
                        // Здесь можно добавить код для обновления UI
                        // Например, обновление позиции графика или других элементов
                    }
                    
                    ticking = false;
                });
                
                ticking = true;
            }

            // Устанавливаем новый таймаут
            scrollTimeout = setTimeout(function() {
                // Код, который нужно выполнить после окончания скролла
                // Например, обновление данных или UI
            }, 150);
        });
    }

    // Применяем оптимизацию к основным скроллируемым элементам
    const scrollableElements = [
        document.querySelector('.params-area'),
        document.querySelector('#model-info'),
        document.querySelector('#setup_selection_list'),
        document.querySelector('#canvas-wrapper')
    ];

    scrollableElements.forEach(element => {
        if (element) {
            optimizeScroll(element);
        }
    });

    // Оптимизация для touch-событий
    let touchStartY = 0;
    let touchEndY = 0;

    document.addEventListener('touchstart', function(e) {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    document.addEventListener('touchmove', function(e) {
        touchEndY = e.touches[0].clientY;
    }, { passive: true });

    // Предотвращаем стандартное поведение скролла для canvas
    const canvas = document.querySelector('#graph');
    if (canvas) {
        canvas.addEventListener('touchmove', function(e) {
            if (e.target === canvas) {
                e.preventDefault();
            }
        }, { passive: false });
    }
}); 