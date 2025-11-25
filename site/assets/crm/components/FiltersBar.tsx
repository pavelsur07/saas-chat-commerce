import React from 'react';
type Filters = {
  assignee: string | 'all';
  channel: string | 'all';
  q: string;
  onlyWebForms: boolean;
  utmCampaign: string;
};
export default function FiltersBar({ value, onChange }: { value: Filters; onChange: (v: Filters) => void }) {
  return (
    <div className="flex items-center gap-2">
      <div className="inline-flex rounded-2xl border border-gray-200 bg-white p-1">
        {['all','Мария Савельева','Игорь К.'].map(a => (
          <button key={a}
            onClick={() => onChange({ ...value, assignee: a as Filters['assignee'] })}
            className={`px-3 py-1.5 rounded-lg text-sm transition ${value.assignee === a ? 'bg-gray-900 text-white' : 'hover:bg-gray-100'}`}>
            {a === 'all' ? 'Все' : a}
          </button>
        ))}
      </div>
      <div className="inline-flex rounded-2xl border border-gray-200 bg-white p-1">
        {['all','telegram','whatsapp','instagram','email','website_chat'].map(c => (
          <button key={c}
            onClick={() => onChange({ ...value, channel: c as Filters['channel'] })}
            className={`px-3 py-1.5 rounded-lg text-sm transition ${value.channel === c ? 'bg-gray-900 text-white' : 'hover:bg-gray-100'}`}>
            {c === 'all' ? 'Все каналы' : c}
          </button>
        ))}
      </div>
      <input
        value={value.q}
        onChange={(e) => onChange({ ...value, q: e.target.value })}
        className="w-full max-w-xs px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
        placeholder="Поиск по сделкам"
      />
      <input
        value={value.utmCampaign}
        onChange={(e) => onChange({ ...value, utmCampaign: e.target.value })}
        className="w-full max-w-xs px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
        placeholder="UTM-кампания"
      />
      <button
        type="button"
        onClick={() => onChange({ ...value, onlyWebForms: !value.onlyWebForms })}
        className={
          'px-3 py-1.5 rounded-xl text-xs border ' +
          (value.onlyWebForms
            ? 'bg-gray-900 text-white border-gray-900'
            : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100')
        }
      >
        Только заявки с форм
      </button>
    </div>
  );
}
