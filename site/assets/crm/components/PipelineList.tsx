// site/assets/crm/components/PipelineList.tsx
import React, { useEffect, useState } from 'react';
import axios from 'axios';
import PipelineCreateModal from './PipelineCreateModal';

type Pipeline = { id: string; name: string };
type Props = { activeId: string | null; onSelect: (pipeline: Pipeline) => void; };

export default function PipelineList({ activeId, onSelect }: Props) {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);

  useEffect(() => {
    axios.get<Pipeline[]>('/api/crm/pipelines')
      .then(({ data }) => {
        setPipelines(data);
      })
      .catch(() => setPipelines([]));
  }, []);

  useEffect(() => {
    if (!activeId && pipelines.length > 0) {
      onSelect(pipelines[0]);
    }
  }, [activeId, pipelines, onSelect]);

  const [isCreateModalOpen, setCreateModalOpen] = useState(false);

  const handlePipelineCreated = (pipeline: Pipeline) => {
    setPipelines((prev) => [...prev, pipeline]);
    onSelect(pipeline);
    setCreateModalOpen(false);
    if (window.confirm('Перейти к редактору этапов?')) {
      window.location.href = `/crm/pipelines/${pipeline.id}/stages`;
    }
  };

  return (
    <div className="space-y-2">
      <button
        onClick={() => setCreateModalOpen(true)}
        className="w-full px-3 py-2 rounded-xl border bg-white hover:bg-gray-50"
      >
        + Добавить воронку
      </button>

      {pipelines.map((p) => (
        <div
          key={p.id}
          className={`px-3 py-2 rounded-xl border ${p.id === activeId ? 'bg-white border-black' : 'bg-white/60 border-transparent hover:border-gray-300'}`}
        >
          <div className="flex items-center justify-between">
            <button onClick={() => onSelect(p)} className="font-semibold text-left">{p.name}</button>
            <a
              href={`/crm/pipelines/${p.id}/stages`}
              className="inline-flex items-center justify-center rounded-full p-1 text-blue-600 hover:text-blue-800"
              aria-label="Редактировать этапы"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                className="h-5 w-5"
              >
                <circle cx="12" cy="12" r="3.25" />
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M12 3.75v1.5M12 18.75v1.5M5.47 5.47l1.06 1.06M17.47 17.47l1.06 1.06M3.75 12h1.5M18.75 12h1.5M5.47 18.53l1.06-1.06M17.47 6.53l1.06-1.06"
                />
              </svg>
            </a>
          </div>
        </div>
      ))}

      <PipelineCreateModal
        open={isCreateModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onCreated={handlePipelineCreated}
      />
    </div>
  );
}
