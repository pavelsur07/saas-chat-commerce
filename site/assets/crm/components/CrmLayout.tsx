import React, { useCallback, useState } from 'react';
import PipelineList from './PipelineList';
import FiltersBar from './FiltersBar';
import DealBoard from './DealBoard';
import DealProfile from './DealProfile';
import DealCreateModal from './DealCreateModal';

export type Deal = {
  id: string;
  title: string;
  value?: number;
  amount?: number | null;
  client?: { id: number; name: string; channels?: Array<{ type: string; identifier: string }> } | null;
  channelType?: string;
  unread?: number;
  assignee?: string;
  pipelineId?: string;
  stageId?: string;
  updatedAt?: number;
  daysInStage?: number;
  stage?: { id: string; name: string };
  pipeline?: { id: string; name: string };
  stageEnteredAt?: string;
  source?: string | null;
  meta?: any;
  openedAt?: string;
  createdAt?: string;
};

type PipelineSummary = { id: string; name: string };

export default function CrmLayout() {
  const [activePipeline, setActivePipeline] = useState<PipelineSummary | null>(null);
  const [activeDeal, setActiveDeal] = useState<Deal | null>(null);
  const [filters, setFilters] = useState<{
    assignee: string | 'all';
    channel: string | 'all';
    q: string;
    onlyWebForms: boolean;
    utmCampaign: string;
  }>({
    assignee: 'all',
    channel: 'all',
    q: '',
    onlyWebForms: false,
    utmCampaign: '',
  });
  const [isCreateModalOpen, setCreateModalOpen] = useState(false);
  const [boardReloadKey, setBoardReloadKey] = useState(0);

  const activePipelineId = activePipeline?.id ?? null;

  const handleSelectPipeline = useCallback((pipeline: PipelineSummary) => {
    setActivePipeline(pipeline);
    setActiveDeal(null);
  }, []);

  const handleDealCreated = useCallback((deal: Deal) => {
    setCreateModalOpen(false);
    setBoardReloadKey((prev) => prev + 1);
    setActiveDeal(deal);
  }, []);

  const canCreateDeal = Boolean(activePipelineId);

  return (
    <div className="h-full grid grid-cols-12 gap-4">
      <aside className="col-span-2 rounded-2xl border border-gray-200 bg-white px-3 py-2">
        <div className="flex items-center gap-2 mb-3"><div className="text-lg font-semibold">Воронки</div></div>
        <PipelineList activeId={activePipelineId} onSelect={handleSelectPipeline} />
      </aside>

      <main className="col-span-7 rounded-2xl border border-gray-200 bg-white px-3 py-2 flex flex-col">
        <div className="flex items-center gap-3 border-b border-gray-200 pb-2 mb-2">
          <FiltersBar value={filters} onChange={setFilters} />
          <button
            type="button"
            onClick={() => setCreateModalOpen(true)}
            disabled={!canCreateDeal}
            className={`ml-auto px-3 py-2 rounded-xl transition font-semibold ${canCreateDeal ? 'bg-black text-white hover:bg-black/90' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
          >
            Новая сделка
          </button>
        </div>
        <div className="flex-1 overflow-auto pt-2">
          <DealBoard pipelineId={activePipelineId} filters={filters} onOpenDeal={(deal) => setActiveDeal(deal)} reloadKey={boardReloadKey} />
        </div>
      </main>

      <aside className="col-span-3 rounded-2xl border border-gray-200 bg-white px-3 py-2">
        <DealProfile deal={activeDeal} />
      </aside>

      <DealCreateModal
        open={isCreateModalOpen}
        pipeline={activePipeline}
        onClose={() => setCreateModalOpen(false)}
        onCreated={handleDealCreated}
      />
    </div>
  );
}
