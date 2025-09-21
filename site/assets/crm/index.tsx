import React from 'react';
import { createRoot } from 'react-dom/client';
import CrmLayout from './components/CrmLayout';
import StagesEditor from './components/StagesEditor';
import '../styles/app.css';

const mount = () => {
  const stagesEl = document.getElementById('crm-stages-root');
  if (stagesEl) {
    const pipelineId = stagesEl.getAttribute('data-pipeline-id') as string;
    const root = createRoot(stagesEl);
    root.render(<StagesEditor pipelineId={pipelineId} />);
    return;
  }

  const el = document.getElementById('crm-root');
  if (el) {
    const root = createRoot(el);
    root.render(<CrmLayout />);
  }
};

document.addEventListener('turbo:load', mount);
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  setTimeout(mount, 0);
}
