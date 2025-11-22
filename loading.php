<div class="tooth-loading-screen">
    <div class="tooth-container">
        <div class="tooth">
            <div class="tooth-shine-1"></div>
            <div class="tooth-shine-2"></div>
            <div class="tooth-shine-3"></div>
            <div class="sparkles">
                <div class="sparkle"></div>
                <div class="sparkle"></div>
                <div class="sparkle"></div>
            </div>
        </div>
        <div class="tooth-roots">
            <div class="root"></div>
            <div class="root"></div>
        </div>
        <div class="toothbrush">
            <div class="brush-handle"></div>
            <div class="brush-head">
                <div class="bristles">
                    <div class="bristle"></div>
                    <div class="bristle"></div>
                    <div class="bristle"></div>
                    <div class="bristle"></div>
                    <div class="bristle"></div>
                    <div class="bristle"></div>
                    <div class="bristle"></div>
                </div>
                <div class="toothpaste"></div>
            </div>
        </div>
    </div>
    <div class="loading-message">Brushing up your content...</div>
</div>

<script>
        document.addEventListener('DOMContentLoaded', function () {
            const loadingScreen = document.querySelector('.tooth-loading-screen');
            const loadingMessage = document.querySelector('.loading-message');

            let minDisplayTimePassed = false;
            setTimeout(() => { minDisplayTimePassed = true; }, 10);

            const messages = [
                "Polishing your perfect smile...",
                "Aligning your data for a flawless experience...",
                "Filling the gaps with healthy bytes...",
                "Adjusting your bite... almost there!",
                "Rinsing out digital plaque...",
                "Scraping away technical tartar...",
                "Sterilizing your screen for a fresh start...",
                "Brightening your day, one pixel at a time...",
                "Whitening your pixels...",
                "Flossing through the final bits..."
            ];

            const completionMessages = [
                "Your smile is fully loaded!",
                "Ready to brighten your day!",
                "All cleaned up and ready to go!"
            ];

            let messageIndex = 0;

            // Message interval to update loading message
            const messageInterval = setInterval(() => {
                messageIndex = (messageIndex + 1) % messages.length;
                loadingMessage.style.opacity = 0;
                setTimeout(() => {
                    loadingMessage.textContent = messages[messageIndex];
                    loadingMessage.style.opacity = 1;
                }, 300);
            }, 2000);

            // Hide loader once loading is complete
            function hideLoader() {
                if (minDisplayTimePassed) {
                    clearInterval(messageInterval);

                    // Once loading completes, show a final completion message
                    loadingMessage.textContent = completionMessages[Math.floor(Math.random() * completionMessages.length)];

                    loadingScreen.style.opacity = '0';
                    setTimeout(() => loadingScreen.style.display = 'none', 3000);
                } else {
                    setTimeout(hideLoader, 10);
                }
            }

            // Trigger hideLoader when everything is ready
            window.onload = hideLoader;

            // Fallback timeout (8s max for slow connections)
            setTimeout(hideLoader, 8000);
        });
    </script>