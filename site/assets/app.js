import { registerReactControllerComponents } from '@symfony/ux-react';
import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// ===== JS error reporting to backend =====
/*window.onerror = function (message, source, lineno, colno, error) {
    try {
        fetch('/log-js-error', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                message,
                source,
                lineno,
                colno,
                stack: error && error.stack ? String(error.stack) : null,
                userAgent: navigator.userAgent
            }),
            keepalive: true
        });
    } catch (e) {}
};*/

/*window.addEventListener('unhandledrejection', function (event) {
    try {
        fetch('/log-js-error', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                message: 'Unhandled Promise Rejection',
                reason: event && event.reason ? String(event.reason) : null,
                userAgent: navigator.userAgent
            }),
            keepalive: true
        });
    } catch (e) {}
});*/

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));
// registerReactControllerComponents();
