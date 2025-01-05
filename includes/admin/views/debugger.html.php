<?php use WooHive\Config\Constants; ?>

<div id="wmss-debug-window" style="margin: 2rem 0;">
    <h2 style="margin-bottom: 2rem;"><?php esc_html_e( 'Ventana de Depuración', Constants::TEXT_DOMAIN ); ?></h2>
    <div>
        <div id="wmss-debug-messages" class="wmss-debug-messages"></div>
        <button id="wmss-clear-debug" class="wmss-clear-debug button button-primary">Clear Debug</button>
    </div>
</div>

<script>
    class Debugger {
        static debugWindow = null;
        static messagesContainer = null;
        static clearButton = null;

        static init() {
            if (this.debugWindow) return; // If already initialized, do nothing

            this.debugWindow = this.createDebugWindow();
            this.messagesContainer = this.debugWindow.querySelector('.wmss-debug-messages');
            this.clearButton = this.debugWindow.querySelector('.wmss-clear-debug');

            // Event listener for the clear button
            this.clearButton.addEventListener('click', () => this.clearMessages());
        }

        static createDebugWindow() {
            const windowDiv = document.getElementById('wmss-debug-window');
            if (windowDiv) {
                windowDiv.classList.add('wmss-debug-window');
                return windowDiv;
            }
        }

        static appendMessage(message, level = 'info') {
            if (!this.debugWindow) {
                this.init();
            }

            let messageClass = '';

            switch (level) {
                case 'warning':
                    messageClass = 'warning';
                    break;
                case 'error':
                    messageClass = 'error';
                    break;
                case 'info':
                    messageClass = 'info';
                    break;
                case 'success':
                    messageClass = 'success';
                    break;
                default:
                    messageClass = 'info';
                    break;
            }

            const messageElement = document.createElement('p');
            messageElement.classList.add(messageClass);
            messageElement.textContent = message;
            this.messagesContainer.appendChild(messageElement);

            // Scroll to the bottom of the messages container
            this.debugWindow.scrollTop = this.debugWindow.scrollHeight;
        }

        static appendHeader(headerText) {
            if (!this.debugWindow) {
                this.init();
            }

            const headerElement = document.createElement('h3');
            headerElement.classList.add('debug-header');
            headerElement.textContent = headerText;
            this.messagesContainer.appendChild(headerElement);

            // Scroll to the bottom of the messages container
            this.debugWindow.scrollTop = this.debugWindow.scrollHeight;
        }

        static appendFooter(footerText) {
            if (!this.debugWindow) {
                this.init();
            }

            const footerElement = document.createElement('p');
            footerElement.classList.add('debug-footer');
            footerElement.textContent = footerText;
            this.messagesContainer.appendChild(footerElement);

            // Scroll to the bottom of the messages container
            this.debugWindow.scrollTop = this.debugWindow.scrollHeight;
        }

        static clearMessages() {
            if (this.messagesContainer) {
                this.messagesContainer.innerHTML = '';
            }
        }

        static info(message) {
            this.appendMessage(message, 'info');
        }

        static warning(message) {
            this.appendMessage(message, 'warning');
        }

        static error(message) {
            this.appendMessage(message, 'error');
        }

        static success(message) {
            this.appendMessage(message, 'success');
        }

        static header(headerText) {
            this.appendHeader(headerText);
        }

        static footer(footerText) {
            this.appendFooter(footerText);
        }
    }

    Debugger.init();
</script>
