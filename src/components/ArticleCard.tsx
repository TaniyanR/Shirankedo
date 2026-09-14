import React from 'react';
import { Flame, MessageSquare, ThumbsUp, ShieldAlert, ArrowRight } from 'lucide-react';
import { Article } from '../types';
import { ShirankedoGauge } from './ShirankedoGauge';

interface ArticleCardProps {
  article: Article;
  onSelect: (article: Article) => void;
}

export const ArticleCard: React.FC<ArticleCardProps> = ({ article, onSelect }) => {
  const isHeld = article.status === 'on_hold';

  return (
    <article
      onClick={() => onSelect(article)}
      className="group bg-white rounded-2xl border border-stone-200 overflow-hidden shadow-xs hover:shadow-md hover:border-amber-300 transition-all cursor-pointer flex flex-col md:flex-row"
    >
      {/* Thumbnail Area */}
      <div className="relative w-full md:w-56 h-48 md:h-auto shrink-0 bg-stone-100 overflow-hidden">
        {article.imageUrl ? (
          <img
            src={article.imageUrl}
            alt={article.title}
            referrerPolicy="no-referrer"
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-stone-400 bg-stone-100">
            <span className="text-xs font-medium">画像なし</span>
          </div>
        )}

        {/* Rapid rise badge on image */}
        {article.isRapidRise && (
          <div className="absolute top-2.5 left-2.5 bg-red-600/90 backdrop-blur-xs text-white text-[11px] font-extrabold px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
            <Flame className="w-3 h-3 fill-white" />
            <span>急上昇 +{Math.round(article.growthRate)}%</span>
          </div>
        )}

        {/* Held status badge */}
        {isHeld && (
          <div className="absolute inset-0 bg-stone-950/70 backdrop-blur-xs flex flex-col items-center justify-center p-3 text-center text-white">
            <ShieldAlert className="w-7 h-7 text-amber-400 mb-1" />
            <span className="text-xs font-bold text-amber-300">安全ブレーキ作動中 (保留)</span>
            <span className="text-[10px] text-stone-300 mt-0.5 line-clamp-2">{article.dangerReason}</span>
          </div>
        )}
      </div>

      {/* Content Area */}
      <div className="p-4 md:p-5 flex-1 flex flex-col justify-between space-y-3">
        <div>
          {/* Category & Metadata header */}
          <div className="flex items-center justify-between gap-2 mb-2">
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">
                {article.categoryName || '総合'}
              </span>
              <span className="text-xs text-stone-600 font-medium">
                {new Date(article.publishedAt).toLocaleDateString('ja-JP', {
                  month: 'numeric',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </span>
            </div>

            <ShirankedoGauge
              score={article.shirankedoIndex}
              label={article.indexLabel}
              isRapidRise={article.isRapidRise}
              size="sm"
            />
          </div>

          {/* Title */}
          <h2 className="text-lg md:text-xl font-bold text-stone-900 group-hover:text-amber-800 transition-colors leading-snug line-clamp-2">
            {article.title}
          </h2>

          {/* Why Trending Summary */}
          <p className="mt-2 text-xs md:text-sm text-stone-600 line-clamp-2 leading-relaxed">
            <strong className="text-stone-800 font-bold mr-1">【なぜ話題？】</strong>
            {article.whyTrending}
          </p>
        </div>

        {/* Footer info: Trademark closing & Interaction stats */}
        <div className="pt-2 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
          <div className="truncate pr-2 italic text-stone-500 font-serif">
            「…{article.conclusionSentence.slice(-18)}」
          </div>

          <div className="flex items-center gap-3 shrink-0">
            {article.votes && (
              <span className="flex items-center gap-1 text-stone-600">
                <ThumbsUp className="w-3.5 h-3.5 text-stone-400" />
                {article.votes.knew + article.votes.didntKnow}票
              </span>
            )}
            <span className="flex items-center gap-1 font-semibold text-amber-700 group-hover:translate-x-0.5 transition-transform">
              詳しく見る
              <ArrowRight className="w-3.5 h-3.5" />
            </span>
          </div>
        </div>
      </div>
    </article>
  );
};
