import React, { useEffect, useState } from 'react';
type Pipeline = { id: string; name: string };
type Props = { activeId: string | null; onSelect: (id: string) => void; };

export default function PipelineList({ activeId, onSelect }: Props) {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  useEffect(() => { setPipelines([]); }, []);
  return (
    <div className="space-y-2">
      {pipelines.map((p) => (
        <button key={p.id} onClick={() => onSelect(p.id)}
          className={`w-full text-left px-3 py-2 rounded-xl border transition ${
            p.id === activeId ? 'bg-white border-black' : 'bg-white/60 border-transparent hover:border-gray-300'}`}>
          <div className="font-semibold">{p.name}</div>
        </button>
      ))}
    </div>
  );
}
