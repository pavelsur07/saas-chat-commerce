import React, { useEffect, useState } from 'react';
type Props = {
  pipelineId: string | null;
  filters: { assignee: string | 'all'; channel: string | 'all'; q: string };
  onOpenDeal: (deal: any) => void;
};
export default function DealBoard({ pipelineId, filters, onOpenDeal }: Props) {
  const [stages, setStages] = useState<Array<{ id: string; name: string; wip?: number }>>([]);
  const [dealsByStage, setDealsByStage] = useState<Record<string, any[]>>({});
  useEffect(() => { setStages([]); setDealsByStage({}); }, [pipelineId, filters]);
  return (
    <div className="grid grid-cols-3 gap-3">
      {stages.map((s) => (
        <div key={s.id} className="flex flex-col rounded-2xl border bg-white">
          <div className="p-3 border-b"><div className="font-semibold">{s.name}</div></div>
          <div className="p-3 space-y-2">
            {(dealsByStage[s.id] || []).map((d) => (
              <button key={d.id} onClick={() => onOpenDeal(d)} className="rounded-2xl border bg-white p-3 shadow-sm text-left">
                <div className="font-semibold">{d.title}</div>
              </button>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
