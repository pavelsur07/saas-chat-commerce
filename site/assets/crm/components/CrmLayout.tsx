import React, { useState } from 'react';
import PipelineList from './PipelineList';
import FiltersBar from './FiltersBar';
import DealBoard from './DealBoard';
import DealProfile from './DealProfile';

export type Deal = {
  id: string; title: string; value?: number;
  client: { id: number; name: string; channels?: Array<{ type: string; identifier: string }> };
  channelType?: string; unread?: number; assignee?: string;
  pipelineId: string; stageId: string; updatedAt?: number; daysInStage?: number;
};

export default function CrmLayout() {
  const [activePipelineId, setActivePipelineId] = useState<string | null>(null);
  const [activeDeal, setActiveDeal] = useState<Deal | null>(null);
  const [filters, setFilters] = useState<{ assignee: string | 'all'; channel: string | 'all'; q: string }>({
    assignee: 'all', channel: 'all', q: ''
  });

  return (
    <div className="h-full grid grid-cols-12 gap-4">
      <aside className="col-span-2 bg-white rounded-2xl border p-3">
        <div className="flex items-center gap-2 mb-3"><div className="text-lg font-semibold">Воронки</div></div>
        <PipelineList activeId={activePipelineId} onSelect={setActivePipelineId} />
      </aside>

      <main className="col-span-7 bg-white rounded-2xl border flex flex-col">
        <div className="p-3 border-b flex items-center gap-3">
          <FiltersBar value={filters} onChange={setFilters} />
          <button className="ml-auto px-3 py-2 rounded-xl border hover:bg-gray-50">Новая сделка</button>
        </div>
        <div className="flex-1 p-3 overflow-auto">
          <DealBoard pipelineId={activePipelineId} filters={filters} onOpenDeal={setActiveDeal} />
        </div>
      </main>

      <aside className="col-span-3 bg-white rounded-2xl border p-3">
        <DealProfile deal={activeDeal} />
      </aside>
    </div>
  );
}
