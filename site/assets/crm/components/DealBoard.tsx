import axios from 'axios';
import React, { useEffect, useState } from 'react';

type Stage = {
  id: string;
  name: string;
  position: number;
  color?: string;
  probability?: number;
  isStart?: boolean;
  isWon?: boolean;
  isLost?: boolean;
  slaHours?: number | null;
};

type Deal = {
  id: string;
  title: string;
  stage: Stage;
  pipeline: { id: string; name: string };
  client?: { id: string | number; name?: string; displayName?: string; firstName?: string; lastName?: string; channels?: Array<{ type: string; identifier: string }> } | null;
  stageEnteredAt?: string | null;
  [key: string]: any;
};

type PipelineDetailed = {
  id: string;
  name: string;
  stages: Stage[];
};

type DealsResponse = {
  items: Deal[];
  total: number;
  limit: number;
  offset: number;
};

type Props = {
  pipelineId: string | null;
  filters: { assignee: string | 'all'; channel: string | 'all'; q: string };
  onOpenDeal: (deal: any) => void;
};

type DragPayload = { dealId: string; fromStageId: string };

export default function DealBoard({ pipelineId, filters, onOpenDeal }: Props) {
  const [stages, setStages] = useState<Stage[]>([]);
  const [dealsByStage, setDealsByStage] = useState<Record<string, Deal[]>>({});
  const [draggedDeal, setDraggedDeal] = useState<DragPayload | null>(null);

  useEffect(() => {
    let cancelled = false;

    if (!pipelineId) {
      setStages([]);
      setDealsByStage({});
      return () => {
        cancelled = true;
      };
    }

    setStages([]);
    setDealsByStage({});

    (async () => {
      try {
        const { data } = await axios.get<PipelineDetailed>(`/api/crm/pipelines/${pipelineId}`);
        if (cancelled) return;

        const sorted = [...(data.stages || [])].sort((a, b) => a.position - b.position);
        setStages(sorted);
      } catch {
        if (!cancelled) {
          setStages([]);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [pipelineId]);

  useEffect(() => {
    let cancelled = false;

    if (!pipelineId) {
      setDealsByStage({});
      return () => {
        cancelled = true;
      };
    }

    const params: Record<string, string | number | undefined> = {
      pipeline: pipelineId,
      limit: 100,
      offset: 0,
    };

    if (filters.assignee !== 'all') {
      params.owner = filters.assignee;
    }

    const trimmedSearch = filters.q.trim();
    if (trimmedSearch !== '') {
      params.search = trimmedSearch;
    }

    axios
      .get<DealsResponse>('/api/crm/deals', { params })
      .then(({ data }) => {
        if (cancelled) return;

        const grouped: Record<string, Deal[]> = {};
        data.items.forEach((deal) => {
          const stageId = deal.stage?.id;
          if (!stageId) return;
          if (!grouped[stageId]) {
            grouped[stageId] = [];
          }
          grouped[stageId].push(deal);
        });

        setDealsByStage(grouped);
      })
      .catch(() => {
        if (!cancelled) {
          setDealsByStage({});
        }
      });

    return () => {
      cancelled = true;
    };
  }, [pipelineId, filters.assignee, filters.channel, filters.q]);

  if (!pipelineId) {
    return <div className="h-full flex items-center justify-center text-sm text-gray-500">Выберите воронку</div>;
  }

  const handleDragStart = (event: React.DragEvent<HTMLButtonElement>, stageId: string, deal: Deal) => {
    const payload: DragPayload = { dealId: deal.id, fromStageId: stageId };
    event.dataTransfer.effectAllowed = 'move';
    try {
      event.dataTransfer.setData('application/json', JSON.stringify(payload));
    } catch {
      // ignore unsupported DataTransfer operations
    }
    setDraggedDeal(payload);
  };

  const handleDragEnd = () => {
    setDraggedDeal(null);
  };

  const handleDrop = async (event: React.DragEvent<HTMLDivElement>, targetStageId: string) => {
    event.preventDefault();

    let payload = draggedDeal;
    if (!payload) {
      try {
        const raw = event.dataTransfer.getData('application/json');
        if (raw) {
          payload = JSON.parse(raw) as DragPayload;
        }
      } catch {
        payload = null;
      }
    }

    if (!payload) return;

    const { dealId, fromStageId } = payload;
    if (fromStageId === targetStageId) {
      setDraggedDeal(null);
      return;
    }

    const sourceList = dealsByStage[fromStageId] || [];
    const deal = sourceList.find((item) => item.id === dealId);
    if (!deal) {
      setDraggedDeal(null);
      return;
    }

    const snapshot: Record<string, Deal[]> = {};
    const stageIds = new Set<string>([...Object.keys(dealsByStage), ...stages.map((stage) => stage.id)]);
    stageIds.forEach((id) => {
      snapshot[id] = [...(dealsByStage[id] || [])];
    });

    const targetStage = stages.find((stage) => stage.id === targetStageId);
    const optimisticDeal: Deal = {
      ...deal,
      stage: targetStage ? { ...deal.stage, ...targetStage } : { ...deal.stage, id: targetStageId },
    };

    setDealsByStage((prev) => {
      const next = { ...prev };
      next[fromStageId] = (prev[fromStageId] || []).filter((item) => item.id !== dealId);
      next[targetStageId] = [optimisticDeal, ...(prev[targetStageId] || [])];
      return next;
    });

    try {
      const { data } = await axios.post<Deal & { stageHistory?: unknown[] }>(`/api/crm/deals/${dealId}/move`, {
        toStageId: targetStageId,
      });

      setDealsByStage((prev) => {
        const next = { ...prev };
        next[targetStageId] = (prev[targetStageId] || []).map((item) => (item.id === dealId ? data : item));
        return next;
      });
    } catch {
      setDealsByStage(snapshot);
    } finally {
      setDraggedDeal(null);
    }
  };

  const allowDrop = (event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
  };

  return (
    <div className="grid grid-cols-3 gap-3">
      {stages.map((stage) => (
        <div
          key={stage.id}
          className="flex flex-col rounded-2xl border border-gray-200 bg-white px-3 py-2"
          onDragOver={allowDrop}
          onDrop={(event) => handleDrop(event, stage.id)}
        >
          <div className="border-b border-gray-200 pb-2 mb-2">
            <div className="font-semibold">{stage.name}</div>
          </div>
          <div className="space-y-2 min-h-[1rem]">
            {(dealsByStage[stage.id] || []).map((deal) => {
              const slaHours = stage.slaHours;
              let isSlaOverdue = false;
              let overdueHours = 0;

              if (slaHours != null && deal.stageEnteredAt) {
                const enteredAtTimestamp = new Date(deal.stageEnteredAt).getTime();
                if (!Number.isNaN(enteredAtTimestamp)) {
                  const hoursInStage = (Date.now() - enteredAtTimestamp) / 3600000;
                  if (hoursInStage > slaHours) {
                    isSlaOverdue = true;
                    overdueHours = Math.max(0, Math.ceil(hoursInStage - slaHours));
                  }
                }
              }

              const className = [
                'w-full rounded-2xl border border-gray-200 bg-white px-3 py-2 shadow-sm text-left relative',
                isSlaOverdue ? 'ring-1 ring-rose-300' : '',
              ]
                .filter(Boolean)
                .join(' ');

              return (
                <button
                  key={deal.id}
                  type="button"
                  draggable
                  onDragStart={(event) => handleDragStart(event, stage.id, deal)}
                  onDragEnd={handleDragEnd}
                  onClick={() => onOpenDeal(deal)}
                  className={className}
                >
                  {isSlaOverdue && (
                    <span
                      className="absolute right-3 top-3 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                      title={`SLA просрочен на ${overdueHours} ч`}
                    >
                      SLA
                    </span>
                  )}
                  <div className="font-semibold">{deal.title}</div>
                </button>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}
