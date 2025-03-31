// Оптимизация для ландшафтной ориентации
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем, является ли устройство мобильным
    const isMobile = window.innerWidth <= 768;
    if (!isMobile) return; // Выходим, если это не мобильное устройство

    let isLandscape = window.innerHeight < window.innerWidth;
    let resizeTimeout;

    // Функция для обновления размеров графика
    function updateGraphSize() {
        const canvas = document.getElementById('graph');
        const wrapper = document.getElementById('canvas-wrapper');
        
        if (!canvas || !wrapper) return;

        // Получаем размеры контейнера
        const wrapperWidth = wrapper.clientWidth;
        const wrapperHeight = wrapper.clientHeight;

        // Устанавливаем размеры canvas
        canvas.width = wrapperWidth;
        canvas.height = wrapperHeight;

        // Обновляем размеры в настройках графика
        if (typeof Graph_settings !== 'undefined') {
            Graph_settings.width = wrapperWidth;
            Graph_settings.height = wrapperHeight;
        }

        // Перерисовываем график
        if (typeof drawGraph === 'function') {
            drawGraph();
        }
    }

    // Обработчик изменения ориентации
    window.addEventListener('orientationchange', function() {
        // Даем время на завершение анимации поворота
        setTimeout(function() {
            isLandscape = window.innerHeight < window.innerWidth;
            updateGraphSize();
        }, 100);
    });

    // Оптимизированный обработчик изменения размера окна
    window.addEventListener('resize', function() {
        // Проверяем, осталось ли устройство мобильным
        if (window.innerWidth > 768) return;

        // Отменяем предыдущий таймаут
        if (resizeTimeout) {
            clearTimeout(resizeTimeout);
        }

        // Устанавливаем новый таймаут
        resizeTimeout = setTimeout(function() {
            updateGraphSize();
        }, 150);
    });

    // Оптимизация touch-событий для графика
    const canvas = document.getElementById('graph');
    if (canvas) {
        let touchStartX = 0;
        let touchStartY = 0;
        let isScrolling = false;
        let scrollStartY = 0;
        let initialTouchY = 0;
        let lastTouchY = 0;
        let touchStartTime = 0;
        const SCROLL_THRESHOLD = 10; // Минимальное расстояние для определения скролла
        const SCROLL_TIME_THRESHOLD = 50; // Максимальное время для определения скролла

        canvas.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            initialTouchY = touchStartY;
            lastTouchY = touchStartY;
            touchStartTime = Date.now();
            isScrolling = false;
            scrollStartY = 0;
        }, { passive: true });

        canvas.addEventListener('touchmove', function(e) {
            const touchX = e.touches[0].clientX;
            const touchY = e.touches[0].clientY;
            const deltaX = Math.abs(touchX - touchStartX);
            const deltaY = Math.abs(touchY - touchStartY);
            const deltaYFromLast = Math.abs(touchY - lastTouchY);
            lastTouchY = touchY;

            // Если скролл еще не определен
            if (!isScrolling) {
                const timeElapsed = Date.now() - touchStartTime;
                
                // Определяем скролл на основе:
                // 1. Расстояния от начальной точки
                // 2. Времени с начала касания
                // 3. Направления движения
                if (deltaY > SCROLL_THRESHOLD && 
                    timeElapsed < SCROLL_TIME_THRESHOLD && 
                    Math.abs(touchY - initialTouchY) > SCROLL_THRESHOLD) {
                    isScrolling = true;
                    scrollStartY = touchY;
                }
            }

            // Если это скролл, предотвращаем стандартное поведение
            if (isScrolling) {
                e.preventDefault();
            }
        }, { passive: false });

        canvas.addEventListener('touchend', function() {
            isScrolling = false;
            scrollStartY = 0;
        });
    }

    // Инициализация размеров при загрузке
    updateGraphSize();
}); 