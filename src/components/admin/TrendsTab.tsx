import React from 'react';
import { Flame, Sparkles, RefreshCw, ExternalLink } from 'lucide-react';
import { TrendCandidate } from '../../types';

interface TrendsTabProps {
  trends: TrendCandidate[];
  onGenerateArticle: (trendId: number, keyword: string) => void;
  onRefresh: () => void;
  isGenerating: boolean;
}

export const TrendsTab: React.FC<TrendsTabProps> = ({
  trends,
  onGenerateArticle,
  onRefresh,
  isGenerating,
}) => {
  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「急上昇トレンド候補一覧」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">急上昇トレンド候補一覧</h2>
            <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 text-[11px] font-bold">
              {trends.length} 件 待機中
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            Googleトレンド・SNS等から自動収集された最新話題ワードとワンクリック記事執筆の候補です。
          </p>
        </div>
        <button
          type="button"
          onClick={onRefresh}
          className="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer"
        >
          <RefreshCw className="w-3.5 h-3.5 text-stone-500" />
          <span>最新化</span>
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {trends.map((t) => (
          <div
            key={t.id}
            className="bg-white rounded-3xl p-5 border border-stone-200 shadow-xs flex flex-col justify-between hover:border-amber-300 transition-all group space-y-4"
          >
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-stone-100 text-stone-600">
                  {t.source}
                </span>
                <span className="text-xs font-black text-amber-600 font-mono">
                  {t.velocityScore} pt
                </span>
              </div>
              <h3 className="font-black text-sm text-stone-900 group-hover:text-amber-800 transition-colors">
                {t.keyword}
              </h3>
              <p className="text-xs text-stone-500 line-clamp-2">
                {t.context || 'SNSや検索エンジンで急速に言及数が伸びているホットキーワードです。'}
              </p>
            </div>

            <div className="pt-3 border-t border-stone-100 flex items-center justify-between">
              <span className="text-[10px] text-stone-400 font-mono">
                {new Date(t.discoveredAt).toLocaleTimeString('ja-JP')}
              </span>
              <button
                type="button"
                onClick={() => onGenerateArticle(t.id, t.keyword)}
                disabled={isGenerating}
                className="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs transition-colors cursor-pointer disabled:opacity-50 flex items-center gap-1 shadow-xs"
              >
                <Sparkles className="w-3.5 h-3.5" />
                <span>AI記事を執筆</span>
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
