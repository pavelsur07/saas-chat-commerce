import React from 'react';
type Filters = { assignee: string | 'all'; channel: string | 'all'; q: string };
export default function FiltersBar({ value, onChange }: { value: Filters; onChange: (v: Filters) => void }) {
  return (
    <div className="flex items-center gap-2">
      <div className="inline-flex rounded-xl border border-gray-200 bg-white p-1">
        {['all','Мария Савельева','Игорь К.'].map(a => (
          <button key={a}
            onClick={() => onChange({ ...value, assignee: a as Filters['assignee'] })}
            className={`px-3 py-1.5 rounded-lg text-sm transition ${value.assignee === a ? 'bg-gray-900 text-white' : 'hover:bg-gray-100'}`}>
            {a === 'all' ? 'Все' : a}
          </button>
        ))}
      </div>
      <div className="inline-flex rounded-xl border border-gray-200 bg-white p-1">
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
        className="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
        placeholder="Поиск по сделкам"
      />
    </div>
  );
}
