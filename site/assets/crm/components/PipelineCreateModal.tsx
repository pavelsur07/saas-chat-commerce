import React, { useEffect, useState } from 'react';
import axios from 'axios';

type Pipeline = { id: string; name: string };

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: (pipeline: Pipeline) => void;
};

export default function PipelineCreateModal({ open, onClose, onCreated }: Props) {
  const [name, setName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }
    setName('');
    setError(null);
    setSaving(false);
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        if (!saving) {
          onClose();
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [open, onClose, saving]);

  if (!open) {
    return null;
  }

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    const trimmedName = name.trim();
    if (!trimmedName) {
      setError('Укажите название воронки');
      return;
    }

    setSaving(true);
    setError(null);
    try {
      const { data } = await axios.post<Pipeline>('/api/crm/pipelines', { name: trimmedName });
      setSaving(false);
      onCreated(data);
    } catch (err: any) {
      setSaving(false);
      setError(err?.response?.data?.error || 'Не удалось создать воронку');
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
      <div className="relative z-10 w-full max-w-md rounded-3xl bg-white p-6 shadow-xl">
        <div className="mb-4">
          <div className="text-lg font-semibold">Новая воронка</div>
          <div className="mt-1 text-sm text-gray-500">
            Задайте название, чтобы сразу приступить к настройке этапов
          </div>
        </div>
        {error && (
          <div className="mb-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}
        <form className="space-y-4" onSubmit={handleSubmit}>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Название воронки</label>
            <input
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="Например, Продажи"
              className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-black focus:outline-none focus:ring-1 focus:ring-black"
              autoFocus
            />
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
              disabled={saving}
              className={`rounded-xl px-4 py-2 text-sm font-semibold text-white transition ${saving ? 'bg-gray-300 cursor-not-allowed' : 'bg-black hover:bg-black/90'}`}
            >
              {saving ? 'Создание…' : 'Создать'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
