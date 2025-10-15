import React from 'react';
import { createRoot, Root } from 'react-dom/client';
import ChatLayout from './components/ChatLayout';
import '../styles/app.css'; // Если используете Tailwind

let root: Root | null = null;

const mountChat = () => {
    const rootElement = document.getElementById('chat-center-root');
    if (!rootElement) {
        return;
    }

    if (!root) {
        root = createRoot(rootElement);
    }

    root.render(<ChatLayout />);
};

const unmountChat = () => {
    if (root) {
        root.unmount();
        root = null;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountChat, { once: true });
} else {
    mountChat();
}

document.addEventListener('turbo:load', mountChat);
document.addEventListener('turbo:before-cache', unmountChat);
