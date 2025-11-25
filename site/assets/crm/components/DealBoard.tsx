// site/assets/crm/components/DealBoard.tsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';

type Props = {
  pipelineId: string | null;
  filters: {
    assignee: string | 'all';
    channel: string | 'all';
    q: string;
    onlyWebForms: boolean;
    utmCampaign: string;
  };
  onOpenDeal: (deal: any) => void;
  reloadKey?: number;
};

type StageCol = { id: string; name: string; position: number; slaHours: number | null };
type Deal = {
  id: string;
  title: string;
  stageId: string;
  stageEnteredAt?: string;
  source?: string;
  client?: { name?: string | null } | null;
  openedAt?: string;
  createdAt?: string;
};

export default function DealBoard({ pipelineId, filters, onOpenDeal, reloadKey = 0 }: Props) {
  const [stages, setStages] = useState<StageCol[]>([]);
  const [dealsByStage, setDealsByStage] = useState<Record<string, Deal[]>>({});
  const [error, setError] = useState<string | null>(null);

  const dragDeal = useRef<Deal | null>(null);

  const load = async () => {
    if (!pipelineId) { setStages([]); setDealsByStage({}); return; }
    try {
      const { data: pipeline } = await axios.get<{ stages: StageCol[] }>(`/api/crm/pipelines/${pipelineId}`);
      const cols = (pipeline.stages || []).slice().sort((a: any, b: any) => a.position - b.position)
        .map((s: any) => ({ id: s.id, name: s.name, position: s.position, slaHours: s.slaHours ?? null }));
      setStages(cols);

      const params: Record<string, string | number> = { pipeline: pipelineId, limit: 100, offset: 0 };
      if (filters.assignee && filters.assignee !== 'all') { params.owner = filters.assignee; }
      if (filters.q) { params.search = filters.q; }

      if (filters.onlyWebForms) {
        params.onlyWebForms = 1;
      }

      if (filters.utmCampaign) {
        params.utmCampaign = filters.utmCampaign;
      }

      const { data: dealsResp } = await axios.get<{ items: Deal[] }>(`/api/crm/deals`, {
        params,
      });
      const grouped: Record<string, Deal[]> = {};
      (dealsResp.items || []).forEach((d: Deal) => {
        const stageKey = (d as any).stageId || (d as any).stage?.id;
        if (!stageKey) { return; }
        grouped[stageKey] ||= [];
        grouped[stageKey].push({ ...d, stageId: stageKey });
      });
      setDealsByStage(grouped);
      setError(null);
    } catch (e: any) {
      setError(e?.response?.data?.error || e.message || 'Ошибка загрузки доски');
    }
  };

  useEffect(() => {
    load();
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [pipelineId, reloadKey, filters.assignee, filters.q, filters.onlyWebForms, filters.utmCampaign]);

  const onCardDragStart = (deal: Deal) => (e: React.DragEvent) => {
    dragDeal.current = deal;
    e.dataTransfer.effectAllowed = 'move';
  };
  const onColDragOver = (e: React.DragEvent) => { e.preventDefault(); };

  const onColDrop = (stageId: string) => async (e: React.DragEvent) => {
    e.preventDefault();
    const deal = dragDeal.current; dragDeal.current = null;
    if (!deal || deal.stageId === stageId) return;
    try {
      // Оптимистично переставим
      setDealsByStage(prev => {
        const next: Record<string, Deal[]> = JSON.parse(JSON.stringify(prev || {}));
        next[deal.stageId] = (next[deal.stageId] || []).filter(d => d.id !== deal.id);
        deal.stageId = stageId;
        next[stageId] = [deal, ...(next[stageId] || [])];
        return next;
      });

      await axios.post(`/api/crm/deals/${deal.id}/move`, { toStageId: stageId });
    } catch (e: any) {
      setError(e?.response?.data?.error || e.message || 'Не удалось перенести сделку');
      // Перезагрузим колонны из источника правды
      load();
    }
  };

  const isSlaOverdue = (deal: Deal, stage: StageCol) => {
    if (!stage.slaHours || !deal.stageEnteredAt) return false;
    const ageH = (Date.now() - new Date(deal.stageEnteredAt).getTime()) / 3600000;
    return ageH > stage.slaHours;
  };

  const sortedStages = useMemo(() => stages.slice().sort((a, b) => a.position - b.position), [stages]);

  return (
    <div className="flex h-full flex-col">
      <div className="mb-3 shrink-0">
        {error && <div className="text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-2">{error}</div>}
      </div>
      <div className="flex-1 overflow-x-auto">
        <div className="flex h-full items-start gap-3 pb-3">
          {sortedStages.map((s) => (
            <div
              key={s.id}
              className="flex h-full min-w-[18rem] shrink-0 flex-col rounded-2xl border bg-white"
              onDragOver={onColDragOver}
              onDrop={onColDrop(s.id)}
            >
              <div className="p-3 border-b"><div className="font-semibold">{s.name}</div></div>
              <div className="p-3 flex flex-1 flex-col gap-2 min-h-24 overflow-y-auto">
                {(dealsByStage[s.id] || []).map((d) => {
                  const sla = isSlaOverdue(d, s);
                  const clientName = d.client?.name || (d as any).clientName || '...';
                  const openedAt = d.openedAt || (d as any).openedAt || d.createdAt || (d as any).createdAt;
                  const openedDate = openedAt ? new Date(openedAt) : null;
                  const openedDateStr = openedDate && !isNaN(openedDate.getTime())
                    ? openedDate.toLocaleDateString('ru-RU')
                    : null;
                  const titleLine = openedDateStr ? `${clientName} · ${openedDateStr}` : clientName;
                  return (
                    <button
                      key={d.id}
                      onClick={() => onOpenDeal(d)}
                      draggable
                      onDragStart={onCardDragStart(d)}
                      className={`relative block w-full rounded-2xl border bg-white p-3 shadow-sm text-left ${sla ? 'ring-1 ring-rose-300' : ''}`}
                      title={sla ? 'SLA просрочен' : undefined}
                    >
                      {sla && <span className="absolute top-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">SLA</span>}
                      <div className="font-semibold">{titleLine}</div>
                      {d.source?.startsWith('web_form:') && (
                        <div className="mt-1 text-xs text-blue-600">С сайта (форма)</div>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
