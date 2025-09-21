import React, { useEffect, useState } from 'react';
import axios from 'axios';

type Pipeline = { id: string; name: string };
type Props = { activeId: string | null; onSelect: (id: string) => void; };

export default function PipelineList({ activeId, onSelect }: Props) {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);

  useEffect(() => {
    axios.get<Pipeline[]>('/api/crm/pipelines')
      .then(({ data }) => setPipelines(data))
      .catch(() => setPipelines([]));
  }, []);

  const createPipeline = async () => {
    const name = window.prompt('Название воронки');
    if (!name) return;
    try {
      const { data } = await axios.post<Pipeline>('/api/crm/pipelines', { name: name.trim() });
      setPipelines(prev => [...prev, data]);
      onSelect(data.id);
      if (window.confirm('Перейти к редактору этапов?')) {
        window.location.href = `/crm/pipelines/${data.id}/stages`;
      }
    } catch {
      alert('Не удалось создать воронку');
    }
  };

  return (
    <div className="space-y-2">
      <button onClick={createPipeline} className="w-full px-3 py-2 rounded-2xl border border-gray-300 bg-white hover:bg-gray-50">
        + Добавить воронку
      </button>

      {pipelines.map((p) => (
        <div
          key={p.id}
          className={`rounded-2xl border px-3 py-2 transition ${
            p.id === activeId
              ? 'border-gray-900 bg-white shadow-sm'
              : 'border-gray-200 bg-white hover:border-gray-300'
          }`}
        >
          <div className="flex items-center justify-between">
            <button onClick={() => onSelect(p.id)} className="font-semibold text-left">{p.name}</button>
            <a href={`/crm/pipelines/${p.id}/stages`} className="text-sm text-blue-600 hover:underline">Редактировать этапы</a>
          </div>
        </div>
      ))}
    </div>
  );
}
