import React from 'react';
import { Flame, Clock, TrendingUp } from 'lucide-react';

interface ShirankedoGaugeProps {
  score: number;
  label?: string;
  isRapidRise?: boolean;
  growthRate?: number;
  firstDetectedAt?: string;
  size?: 'sm' | 'md' | 'lg';
}

export const ShirankedoGauge: React.FC<ShirankedoGaugeProps> = ({
  score,
  label,
  isRapidRise = false,
  growthRate = 0,
  firstDetectedAt,
  size = 'md',
}) => {
  // Determine computed label if not provided
  const computedLabel =
    label ||
    (score >= 80
      ? 'めっちゃ話題'
      : score >= 60
      ? 'かなり話題'
      : score >= 30
      ? '話題'
      : 'ちょい話題');

  // Elapsed time formatter
  const formatElapsedTime = (dateString?: string) => {
    if (!dateString) return '話題発生中';
    const firstTime = new Date(dateString).getTime();
    const diffSec = Math.floor((Date.now() - firstTime) / 1000);

    if (diffSec < 3600) {
      const mins = Math.max(1, Math.floor(diffSec / 60));
      return `話題発生から ${mins}分`;
    } else if (diffSec < 86400) {
      const hours = Math.floor(diffSec / 3600);
      return `話題発生から ${hours}時間`;
    } else {
      const days = Math.floor(diffSec / 86400) + 1;
      return `話題継続 ${days}日目`;
    }
  };

  // Color mapping based on score
  const getBadgeColors = () => {
    if (score >= 80) {
      return {
        bg: 'bg-red-500',
        text: 'text-red-700',
        lightBg: 'bg-red-50',
        border: 'border-red-200',
        bar: 'bg-gradient-to-r from-amber-500 to-red-500',
      };
    } else if (score >= 60) {
      return {
        bg: 'bg-amber-500',
        text: 'text-amber-800',
        lightBg: 'bg-amber-50',
        border: 'border-amber-200',
        bar: 'bg-amber-500',
      };
    } else {
      return {
        bg: 'bg-blue-500',
        text: 'text-blue-700',
        lightBg: 'bg-blue-50',
        border: 'border-blue-200',
        bar: 'bg-blue-500',
      };
    }
  };

  const colors = getBadgeColors();

  if (size === 'sm') {
    return (
      <div className="inline-flex items-center gap-1.5">
        <span className={`text-xs font-black px-2 py-0.5 rounded-full text-white ${colors.bg}`}>
          {score}
        </span>
        <span className="text-xs font-bold text-stone-700">{computedLabel}</span>
        {isRapidRise && (
          <span className="text-[11px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded border border-red-200 flex items-center gap-0.5">
            <Flame className="w-3 h-3 fill-red-500 text-red-500" />
            急上昇
          </span>
        )}
      </div>
    );
  }

  return (
    <div className={`rounded-xl border ${colors.border} ${colors.lightBg} p-3.5 space-y-2.5`}>
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <div className="flex items-center gap-2">
          <div className="flex items-baseline gap-1">
            <span className="text-xs font-bold text-stone-500 tracking-wide uppercase">しらんけど指数</span>
            <span className="text-3xl font-black text-stone-900 tracking-tight">{score}</span>
            <span className="text-xs text-stone-500 font-medium">/ 100</span>
          </div>
          <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${colors.text} bg-white border ${colors.border} shadow-2xs`}>
            {computedLabel}
          </span>
        </div>

        {/* Rapid rise indicator */}
        {isRapidRise && (
          <div className="flex items-center gap-1 text-xs font-extrabold text-red-600 bg-red-100/90 px-2.5 py-1 rounded-full border border-red-300 shadow-2xs animate-pulse">
            <Flame className="w-3.5 h-3.5 fill-red-600" />
            <span>急上昇中</span>
            {growthRate > 0 && <span className="font-mono">+{Math.round(growthRate)}%</span>}
          </div>
        )}
      </div>

      {/* Progress meter bar */}
      <div className="w-full bg-stone-200/80 rounded-full h-2.5 overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-700 ease-out ${colors.bar}`}
          style={{ width: `${Math.min(100, Math.max(5, score))}%` }}
        />
      </div>

      {/* Elapsed time label */}
      <div className="flex items-center justify-between text-[11px] text-stone-500">
        <span className="flex items-center gap-1 font-medium">
          <Clock className="w-3 h-3 text-stone-400" />
          {formatElapsedTime(firstDetectedAt)}
        </span>
        <span className="text-stone-400">独自複合アルゴリズム集計</span>
      </div>
    </div>
  );
};
