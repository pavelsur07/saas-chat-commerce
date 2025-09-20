import React from 'react';
import { createRoot } from 'react-dom/client';
import CrmLayout from './components/CrmLayout';
import '../styles/app.css';

const mount = () => {
  const el = document.getElementById('crm-root');
  if (!el) return;
  const root = createRoot(el);
  root.render(<CrmLayout />);
};

document.addEventListener('turbo:load', mount);
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  setTimeout(mount, 0);
}
