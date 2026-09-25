import React from 'react';
import { ShieldAlert, CheckCircle, Trash2, Eye, AlertTriangle } from 'lucide-react';
import { Article } from '../../types';

interface HeldArticlesTabProps {
  articles: Article[];
  onUpdateStatus: (id: number, status: string) => void;
  onRefresh: () => void;
}

export const HeldArticlesTab: React.FC<HeldArticlesTabProps> = ({
  articles,
  onUpdateStatus,
  onRefresh,
}) => {
  const heldArticles = articles.filter((a) => a.status === 'on_hold');

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「危険・保留記事の審査」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">危険・保留記事の審査</h2>
            <span className="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[11px] font-bold">
              {heldArticles.length} 件 保留中
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            過激ワード、炎上懸念、名誉毀損リスクなど安全ブレーキにより自動保留された記事を人手で審査・承認します。
          </p>
        </div>
      </div>

      {heldArticles.length === 0 ? (
        <div className="bg-white rounded-3xl p-12 text-center border border-stone-200 shadow-sm space-y-3">
          <div className="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
            <CheckCircle className="w-6 h-6" />
          </div>
          <h3 className="font-bold text-stone-800 text-sm">現在、審査待ちの保留記事はありません</h3>
          <p className="text-xs text-stone-500 max-w-sm mx-auto">
            すべてのAI生成記事が安全基準を満たしているか、すでに審査が完了しています。
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {heldArticles.map((article) => (
            <div
              key={article.id}
              className="bg-white rounded-3xl p-6 border border-rose-200 shadow-sm space-y-4"
            >
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 pb-3">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded bg-rose-100 text-rose-800 text-[10px] font-bold">
                    ⚠️ 安全ブレーキ作動
                  </span>
                  <span className="text-xs font-mono text-stone-400">
                    {new Date(article.publishedAt).toLocaleString('ja-JP')}
                  </span>
                </div>
                <div className="text-xs font-bold text-amber-700 font-mono">
                  しらんけど指数: {article.shirankedoIndex}点
                </div>
              </div>

              <div>
                <h4 className="text-base font-black text-stone-900">{article.title}</h4>
                <div className="bg-stone-50 rounded-2xl p-4 mt-3 border border-stone-200 text-xs text-stone-700 leading-relaxed whitespace-pre-wrap">
                  {article.content}
                </div>
              </div>

              <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                <div className="text-xs text-stone-500">
                  検知キーワード: <span className="font-bold text-stone-800">{article.sourceKeyword || '要確認語句'}</span>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => onUpdateStatus(article.id, 'archived')}
                    className="px-3.5 py-2 rounded-xl bg-stone-100 hover:bg-rose-50 text-stone-600 hover:text-rose-700 text-xs font-bold transition-colors cursor-pointer flex items-center gap-1.5"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                    <span>却下・削除</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => onUpdateStatus(article.id, 'published')}
                    className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black transition-colors cursor-pointer shadow-xs flex items-center gap-1.5"
                  >
                    <CheckCircle className="w-3.5 h-3.5" />
                    <span>承認して即時公開する</span>
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
