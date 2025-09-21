import React, { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';

type UUID = string;

type Stage = {
  id: UUID;
  name: string;
  position: number;
  color: string;
  probability: number;
  isStart: boolean;
  isWon: boolean;
  isLost: boolean;
  slaHours: number | null;
  createdAt?: string;
  updatedAt?: string;
};

type Pipeline = { id: UUID; name: string; stages: Stage[] };

export default function StagesEditor({ pipelineId }: { pipelineId: UUID }) {
  const [stages, setStages] = useState<Stage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);

  const [showCreate, setShowCreate] = useState(false);
  const [editStage, setEditStage] = useState<Stage | null>(null);

  const dragId = useRef<UUID | null>(null);
  const prevOrder = useRef<Stage[] | null>(null);

  const showToast = (t: string) => {
    setToast(t);
    window.setTimeout(() => setToast(null), 1200);
  };

  const load = async () => {
    setLoading(true);
    try {
      const { data } = await axios.get<Pipeline>(`/api/crm/pipelines/${pipelineId}`);
      const sorted = (data.stages || []).slice().sort((a, b) => a.position - b.position);
      setStages(sorted);
      setError(null);
    } catch (e: any) {
      setError(e?.response?.data?.error || e.message || 'Ошибка загрузки');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [pipelineId]);

  const hasStart = useMemo(() => stages.some(s => s.isStart), [stages]);

  // ------ CRUD handlers ------

  const createStage = async (payload: Partial<Stage>) => {
    try {
      // ВАЖНО: StageController требует position >= 0
      const position = stages.length > 0 ? Math.max(...stages.map(s => s.position)) + 1 : 1;
      const body = { ...payload, position };
      const { data } = await axios.post<Stage>(`/api/crm/pipelines/${pipelineId}/stages`, body);
      setStages(prev => [...prev, data].sort((a, b) => a.position - b.position));
      setShowCreate(false);
      showToast('Этап добавлен');
    } catch (e: any) {
      setError(e?.response?.data?.error || e.message || 'Не удалось создать этап');
    }
  };

  const updateStage = async (id: UUID, payload: Partial<Stage>) => {
    try {
      const { data } = await axios.patch<Stage>(`/api/crm/stages/${id}`, payload);
      setStages(prev => prev.map(s => (s.id === id ? data : s)).sort((a, b) => a.position - b.position));
      setEditStage(null);
      showToast('Этап обновлён');
    } catch (e: any) {
      setError(e?.response?.data?.error || e.message || 'Не удалось обновить этап');
    }
  };

  const deleteStage = async (id: UUID) => {
    if (!window.confirm('Удалить этап?')) return;
    try {
      await axios.delete(`/api/crm/stages/${id}`);
      const next = stages.filter(s => s.id !== id).map((s, i) => ({ ...s, position: i + 1 }));
      setStages(next);
      showToast('Этап удалён');
    } catch (e: any) {
      const msg = e?.response?.status === 409
        ? (e?.response?.data?.error || 'Нельзя удалить этап с активными сделками')
        : (e?.response?.data?.error || e.message || 'Не удалось удалить этап');
      setError(msg);
    }
  };

  // ------ DnD + reorder ------

  const onDragStart = (e: React.DragEvent, id: UUID) => {
    dragId.current = id;
    prevOrder.current = stages;
    e.dataTransfer.effectAllowed = 'move';
  };
  const onDragOver = (e: React.DragEvent) => { e.preventDefault(); };
  const onDrop = async (e: React.DragEvent, targetId: UUID) => {
    e.preventDefault();
    const sourceId = dragId.current;
    dragId.current = null;
    if (!sourceId || sourceId === targetId) return;

    const next = [...stages];
    const fromIdx = next.findIndex(s => s.id === sourceId);
    const toIdx = next.findIndex(s => s.id === targetId);
    if (fromIdx < 0 || toIdx < 0) return;

    const [moved] = next.splice(fromIdx, 1);
    next.splice(toIdx, 0, moved);

    const withPos = next.map((s, i) => ({ ...s, position: i + 1 }));
    setStages(withPos);

    try {
      const order = withPos.map(s => ({ stageId: s.id, position: s.position }));
      await axios.post(`/api/crm/pipelines/${pipelineId}/stages/reorder`, { order });
      showToast('Порядок этапов обновлён');
    } catch (e: any) {
      if (prevOrder.current) setStages(prevOrder.current);
      setError(e?.response?.data?.error || e.message || 'Не удалось сохранить порядок');
    } finally {
      prevOrder.current = null;
    }
  };

  // ------ Inline form ------

  function StageForm({
    initial, onCancel, onSubmit,
  }: {
    initial?: Partial<Stage>;
    onCancel: () => void;
    onSubmit: (payload: Partial<Stage>) => void;
  }) {
    const [name, setName] = useState(initial?.name ?? '');
    const [color, setColor] = useState(initial?.color ?? '#CBD5E1'); // как на бэке по умолчанию
    const [prob, setProb] = useState<number>(initial?.probability ?? 0);
    const [isStart, setIsStart] = useState<boolean>(initial?.isStart ?? false);
    const [isWon, setIsWon] = useState<boolean>(initial?.isWon ?? false);
    const [isLost, setIsLost] = useState<boolean>(initial?.isLost ?? false);
    const [sla, setSla] = useState<string>(typeof initial?.slaHours === 'number' ? String(initial?.slaHours) : '');
    const [err, setErr] = useState<string | null>(null);
    const submit = (e: React.FormEvent) => {
      e.preventDefault();
      if (!name.trim()) return setErr('Название обязательно');
      if (prob < 0 || prob > 100) return setErr('Вероятность 0–100');
      if (isWon && isLost) return setErr('Нельзя одновременно «Победа» и «Потеря»');
      setErr(null);
      onSubmit({
        name: name.trim(),
        color: color.trim() || '#CBD5E1',
        probability: prob,
        isStart,
        isWon,
        isLost,
        slaHours: sla === '' ? null : Number(sla),
      });
    };
    return (
      <form onSubmit={submit} className="rounded-2xl border border-gray-200 bg-white p-4 space-y-3">
        <div className="flex items-center justify-between">
          <h4 className="font-semibold text-gray-900">{initial?.id ? 'Редактировать этап' : 'Добавить этап'}</h4>
          <button type="button" onClick={onCancel} className="text-gray-500 hover:text-gray-700">×</button>
        </div>
        {err && <div className="text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-2">{err}</div>}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <label className="text-sm">
            <span className="block mb-1 text-gray-600">Название*</span>
            <input className="w-full px-3 py-2 rounded-xl border border-gray-300"
                   value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label className="text-sm">
            <span className="block mb-1 text-gray-600">Цвет (HEX)</span>
            <input className="w-full px-3 py-2 rounded-xl border border-gray-300"
                   value={color} onChange={(e) => setColor(e.target.value)} placeholder="#CBD5E1" />
          </label>
          <label className="text-sm">
            <span className="block mb-1 text-gray-600">Вероятность, %</span>
            <input type="number" min={0} max={100}
                   className="w-full px-3 py-2 rounded-xl border border-gray-300"
                   value={prob} onChange={(e) => setProb(Number(e.target.value))} />
          </label>
          <label className="text-sm">
            <span className="block mb-1 text-gray-600">SLA, часы</span>
            <input type="number" min={0}
                   className="w-full px-3 py-2 rounded-xl border border-gray-300"
                   value={sla} onChange={(e) => setSla(e.target.value)} placeholder="например, 48" />
            <div className="text-xs text-gray-500 mt-1">Используется для подсветки просрочек на канбане.</div>
          </label>
        </div>
        <div className="flex items-center gap-4 pt-1 text-sm text-gray-700">
          <label className="inline-flex items-center gap-2"><input type="checkbox" checked={isStart} onChange={(e) => setIsStart(e.target.checked)} />Старт</label>
          <label className="inline-flex items-center gap-2"><input type="checkbox" checked={isWon} onChange={(e) => setIsWon(e.target.checked)} />Победа</label>
          <label className="inline-flex items-center gap-2"><input type="checkbox" checked={isLost} onChange={(e) => setIsLost(e.target.checked)} />Потеря</label>
        </div>
        <div className="flex items-center gap-2 pt-2">
          <button type="submit" className="px-4 py-2 rounded-xl bg-gray-900 text-white hover:bg-gray-700">Сохранить</button>
          <button type="button" onClick={onCancel} className="px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">Отмена</button>
        </div>
      </form>
    );
  }

  // ------ Render ------

  return (
    <div className="mx-auto max-w-7xl p-6">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h1 className="text-2xl font-bold">Этапы воронки</h1>
          <p className="text-gray-500 text-sm">Pipeline ID: {pipelineId}</p>
        </div>
        <div className="flex items-center gap-3">
          <button className="px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-50" onClick={load}>Обновить</button>
          <button className="px-4 py-2 rounded-xl bg-gray-900 text-white hover:bg-gray-700" onClick={() => { setEditStage(null); setShowCreate(true); }}>Добавить этап</button>
        </div>
      </div>

      {!hasStart && (
        <div className="mb-4 text-sm text-blue-800 bg-blue-50 border border-blue-200 rounded-xl p-3">
          Во воронке должен быть ровно один стартовый этап.
        </div>
      )}

      {error && <div className="mb-4 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-3">{error}</div>}

      {showCreate && !editStage && (
        <div className="mb-4"><StageForm onCancel={() => setShowCreate(false)} onSubmit={createStage} /></div>
      )}
      {editStage && (
        <div className="mb-4"><StageForm initial={editStage} onCancel={() => setEditStage(null)} onSubmit={(p) => updateStage(editStage.id, p)} /></div>
      )}

      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="h-32 rounded-2xl bg-gray-100 animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="flex flex-wrap gap-4">
          {stages.map(s => (
            <div
              key={s.id}
              className="group rounded-2xl shadow p-4 bg-white border border-gray-200 w-80 select-none"
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
                <div className="text-gray-400 cursor-grab" title="Перетащить">⋮⋮</div>
              </div>
              <div className="flex items-center justify-between text-sm text-gray-500">
                <span>Позиция: {s.position}</span>
                <div className="flex gap-2">
                  <button className="px-3 py-1.5 rounded-xl border hover:bg-gray-50" onClick={() => setEditStage(s)}>Ред.</button>
                  <button className="px-3 py-1.5 rounded-xl border border-rose-300 text-rose-700 hover:bg-rose-50" onClick={() => deleteStage(s.id)}>Удалить</button>
                </div>
              </div>
              <div className="mt-2 text-sm text-gray-600">Вероятность: {s.probability}%{typeof s.slaHours === 'number' ? ` • SLA: ${s.slaHours} ч` : ''}</div>
            </div>
          ))}
        </div>
      )}

      {toast && <div className="fixed bottom-4 left-1/2 -translate-x-1/2 px-4 py-2 rounded-xl shadow bg-gray-900 text-white text-sm z-50">{toast}</div>}
    </div>
  );
}
