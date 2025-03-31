// Оптимизация для ландшафтной ориентации
document.addEventListener('DOMContentLoaded', function() {
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

        canvas.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isScrolling = false;
        }, { passive: true });

        canvas.addEventListener('touchmove', function(e) {
            if (!isScrolling) {
                const touchX = e.touches[0].clientX;
                const touchY = e.touches[0].clientY;
                const deltaX = Math.abs(touchX - touchStartX);
                const deltaY = Math.abs(touchY - touchStartY);

                // Определяем, является ли движение скроллом
                isScrolling = deltaY > deltaX;
            }

            if (isScrolling) {
                e.preventDefault();
            }
        }, { passive: false });

        canvas.addEventListener('touchend', function() {
            isScrolling = false;
        });
    }

    // Инициализация размеров при загрузке
    updateGraphSize();
}); 