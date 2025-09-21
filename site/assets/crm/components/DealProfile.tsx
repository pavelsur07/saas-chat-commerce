import React from 'react';

type Deal = {
  id: string;
  title: string;
  value?: number | null;
  amount?: number | string | null;
  client?: { id: number; name: string; channels?: Array<{ type: string; identifier: string }> } | null;
  unread?: number;
  daysInStage?: number;
};

export default function DealProfile({ deal }: { deal: Deal | null }) {
  if (!deal) {
    return <div className="h-full flex items-center justify-center text-sm text-gray-500">Выберите сделку</div>;
  }

  const clientName = deal.client?.name ?? 'Клиент не указан';
  const clientId = deal.client?.id;
  const initials = (clientName || '').split(' ').map((s) => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
  const amount = deal.value ?? (typeof deal.amount === 'number' ? deal.amount : null);

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-start gap-3">
        <div className="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center font-semibold">
          {initials || '??'}
        </div>
        <div className="flex-1">
          <div className="font-semibold leading-tight">{clientName}</div>
          <div className="text-xs text-gray-500">
            {clientId ? `ID клиента: ${clientId}` : 'ID клиента не задан'}
          </div>
        </div>
        <div className="text-right">
          <div className="text-sm font-semibold">{amount ? `${amount} ₽` : ''}</div>
          <div className="text-xs text-gray-500">{deal.title}</div>
        </div>
      </div>
      <div className="mt-4">
        <div className="text-xs uppercase tracking-wide text-gray-500 mb-2">Каналы</div>
        <div className="space-y-2">
          {(deal.client?.channels || []).map((c, i) => (
            <div key={i} className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2">
              <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-700 border border-gray-200">{c.type}</span>
              <div className="text-sm text-gray-700 truncate">{c.identifier}</div>
              <button className="ml-auto text-xs px-2 py-1 rounded-lg border border-gray-300 hover:bg-gray-50">Отключить</button>
            </div>
          ))}
          {(!deal.client || !deal.client.channels || deal.client.channels.length === 0) && (
            <div className="rounded-xl border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-500">
              Каналы не подключены
            </div>
          )}
        </div>
        <button className="mt-2 w-full text-sm px-3 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">Добавить канал</button>
      </div>
      <div className="mt-4">
        <div className="text-xs uppercase tracking-wide text-gray-500 mb-2">Быстрые действия</div>
        <div className="grid grid-cols-2 gap-2">
          <button className="px-3 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">Создать заказ</button>
          <button className="px-3 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">Отправить ссылку</button>
          <button className="px-3 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">Объединить</button>
          <button className="px-3 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">Заметка</button>
        </div>
      </div>
      <div className="mt-auto pt-3 text-xs text-gray-500">Подсказка: карточка сделки откроется из центральной доски.</div>
    </div>
  );
}
