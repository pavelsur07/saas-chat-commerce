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
            <a href={`/crm/pipelines/${p.id}/stages`} className="text-sm text-blue-600 hover:underline">
              Редактировать этапы
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
