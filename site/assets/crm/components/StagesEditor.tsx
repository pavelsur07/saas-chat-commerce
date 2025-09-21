import React, { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';

type UUID = string;

type Stage = {
  id: UUID; name: string; position: number; color: string; probability: number;
  isStart: boolean; isWon: boolean; isLost: boolean; slaHours: number | null;
  createdAt?: string; updatedAt?: string;
};

type Pipeline = { id: UUID; name: string; stages: Stage[] };

export default function StagesEditor({ pipelineId }: { pipelineId: UUID }) {
  const [stages, setStages] = useState<Stage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    axios.get<Pipeline>(`/api/crm/pipelines/${pipelineId}`)
      .then(({ data }) => {
        const sorted = (data.stages || []).slice().sort((a, b) => a.position - b.position);
        setStages(sorted);
        setError(null);
      })
      .catch((e) => setError(e?.response?.data?.message || e.message || 'Ошибка загрузки'))
      .finally(() => setLoading(false));
  }, [pipelineId]);

  const hasStart = useMemo(() => stages.some(s => s.isStart), [stages]);
  const dragId = useRef<UUID | null>(null);

  const onDragStart = (e: React.DragEvent, id: UUID) => { dragId.current = id; e.dataTransfer.effectAllowed = 'move'; };
  const onDragOver  = (e: React.DragEvent) => { e.preventDefault(); };
  const onDrop = (e: React.DragEvent, targetId: UUID) => {
    e.preventDefault();
    const src = dragId.current; dragId.current = null;
    if (!src || src === targetId) return;
    const next = [...stages];
    const from = next.findIndex(s => s.id === src);
    const to   = next.findIndex(s => s.id === targetId);
    if (from < 0 || to < 0) return;
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    setStages(next.map((s, i) => ({ ...s, position: i + 1 })));
  };

  return (
    <div className="mx-auto max-w-7xl p-6">
      <div className="flex items-center justify-between mb-4">
        <h1 className="text-2xl font-bold">Этапы воронки</h1>
      </div>

      {!hasStart && (
        <div className="mb-4 text-sm text-blue-800 bg-blue-50 border border-blue-200 rounded-xl p-3">
          Во воронке должен быть ровно один стартовый этап.
        </div>
      )}

      {error && <div className="mb-4 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-3">{error}</div>}

      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="h-32 rounded-2xl bg-slate-100 animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="flex flex-wrap gap-4">
          {stages.map(s => (
            <div
              key={s.id}
              className="group rounded-2xl shadow p-4 bg-white border border-slate-200 w-80 select-none"
              draggable
              onDragStart={(e) => onDragStart(e, s.id)}
              onDragOver={onDragOver}
              onDrop={(e) => onDrop(e, s.id)}
            >
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <span className="inline-block w-3 h-3 rounded-full" style={{ backgroundColor: s.color }} />
                  <h3 className="text-base font-semibold">{s.name}</h3>
                  {s.isStart && <span className="ml-2 px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-xs">Старт</span>}
                  {s.isWon   && <span className="ml-2 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs">Победа</span>}
                  {s.isLost  && <span className="ml-2 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs">Потеря</span>}
                </div>
                <div className="text-slate-400 cursor-grab" title="Перетащить">⋮⋮</div>
              </div>

              <div className="flex items-center justify-between text-sm text-slate-500">
                <span>Позиция: {s.position}</span>
                <span>Вероятность: {s.probability}%</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
