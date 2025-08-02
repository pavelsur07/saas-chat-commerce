import React from 'react';
import { createRoot } from 'react-dom/client';
import ChatLayout from './components/ChatLayout';
import '../styles/app.css'; // Если используете Tailwind

const renderChat = () => {
    const rootElement = document.getElementById('chat-center-root');
    if (rootElement) {
        const root = createRoot(rootElement);
        root.render(<ChatLayout />);
    }
};

document.addEventListener('turbo:load', renderChat);
