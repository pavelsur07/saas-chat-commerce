import React, { useEffect, useState } from 'react';
import axios from 'axios';

type PipelineSummary = { id: string; name: string };

type Props = {
  open: boolean;
  pipeline: PipelineSummary | null;
  onClose: () => void;
  onCreated: (deal: any) => void;
};

export default function DealCreateModal({ open, pipeline, onClose, onCreated }: Props) {
  const [title, setTitle] = useState('');
  const [amount, setAmount] = useState('');
  const [clientId, setClientId] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!open) return;
    setTitle('');
    setAmount('');
    setClientId('');
    setError(null);
    setSaving(false);
  }, [open, pipeline?.id]);

  useEffect(() => {
    if (!open) return;
    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        if (!saving) {
          onClose();
        }
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose, saving]);

  if (!open) {
    return null;
  }

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!pipeline) {
      setError('Выберите воронку для создания сделки');
      return;
    }
    const trimmedTitle = title.trim();
    if (!trimmedTitle) {
      setError('Укажите название сделки');
      return;
    }

    const payload: Record<string, string> = {
      pipelineId: pipeline.id,
      title: trimmedTitle,
    };
    if (amount.trim() !== '') {
      payload.amount = amount.trim();
    }
    if (clientId.trim() !== '') {
      payload.clientId = clientId.trim();
    }

    setSaving(true);
    setError(null);
    try {
      const { data } = await axios.post('/api/crm/deals', payload);
      setSaving(false);
      onCreated(data);
    } catch (err: any) {
      setSaving(false);
      setError(err?.response?.data?.error || 'Не удалось создать сделку');
    }
  };

  const handleBackdropClick = () => {
    if (!saving) {
      onClose();
    }
  };

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-black/30 backdrop-blur-sm" onClick={handleBackdropClick} />
      <div className="relative z-10 w-full max-w-lg rounded-3xl bg-white p-6 shadow-xl">
        <div className="mb-4">
          <div className="text-lg font-semibold">Новая сделка</div>
          <div className="mt-1 text-sm text-gray-500">
            {pipeline ? `Создание в воронке «${pipeline.name}»` : 'Выберите воронку, чтобы продолжить'}
          </div>
        </div>
        {error && (
          <div className="mb-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}
        <form className="space-y-4" onSubmit={handleSubmit}>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Название сделки</label>
            <input
              type="text"
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              placeholder="Введите название"
              className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-black focus:outline-none focus:ring-1 focus:ring-black"
              autoFocus
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Сумма, ₽</label>
              <input
                type="text"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                placeholder="Необязательно"
                className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-black focus:outline-none focus:ring-1 focus:ring-black"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">ID клиента</label>
              <input
                type="text"
                value={clientId}
                onChange={(event) => setClientId(event.target.value)}
                placeholder="Необязательно"
                className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-black focus:outline-none focus:ring-1 focus:ring-black"
              />
            </div>
          </div>
          <div className="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={handleBackdropClick}
              className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800"
            >
              Отмена
            </button>
            <button
              type="submit"
              disabled={saving || !pipeline}
              className={`rounded-xl px-4 py-2 text-sm font-semibold text-white transition ${saving || !pipeline ? 'bg-gray-300 cursor-not-allowed' : 'bg-black hover:bg-black/90'}`}
            >
              {saving ? 'Создание…' : 'Создать'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
