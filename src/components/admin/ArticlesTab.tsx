import React, { useState } from 'react';
import { FileText, Search, ExternalLink, Trash2, Eye, ShieldAlert, CheckCircle, RefreshCw } from 'lucide-react';
import { Article } from '../../types';

interface ArticlesTabProps {
  articles: Article[];
  onUpdateStatus: (id: number, status: string) => void;
  onRefresh: () => void;
}

export const ArticlesTab: React.FC<ArticlesTabProps> = ({
  articles,
  onUpdateStatus,
  onRefresh,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');

  // 直近10件の公開中最新記事
  const recentPublished = articles
    .filter((a) => a.status === 'published')
    .slice(0, 10);

  // フィルタ済み全記事
  const filteredArticles = articles.filter((a) => {
    const matchesSearch =
      a.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
      a.content.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = categoryFilter === 'all' || a.category === categoryFilter;
    const matchesStatus = statusFilter === 'all' || a.status === statusFilter;
    return matchesSearch && matchesCategory && matchesStatus;
  });

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「記事一覧・管理」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">記事一覧・管理</h2>
            <span className="px-2 py-0.5 rounded-full bg-stone-100 text-stone-800 text-[11px] font-bold">
              全 {articles.length} 本
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            公開中の最新記事 (直近10件) の確認、全記事の検索・ステータス変更（公開 / 保留 / 削除）を一元管理します。
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

      {/* 🌟 公開中・最新記事 (直近10件) ハイライトカード */}
      <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm space-y-4">
        <div className="flex items-center justify-between border-b border-stone-100 pb-3">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-xl bg-amber-500 text-stone-950 font-black flex items-center justify-center text-xs">
              TOP
            </div>
            <div>
              <h3 className="text-sm sm:text-base font-black text-stone-900">
                公開中・最新記事 (直近10件)
              </h3>
              <p className="text-[11px] text-stone-500">
                サイト上で現在公開されている直近10本の最新動向とPV・指数です。
              </p>
            </div>
          </div>
          <span className="text-xs font-bold text-amber-700 font-mono">
            {recentPublished.length}件 表示中
          </span>
        </div>

        <div className="divide-y divide-stone-100">
          {recentPublished.map((article, idx) => (
            <div
              key={article.id}
              className="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-stone-50/80 px-2 rounded-xl transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <span className="w-6 h-6 rounded-md bg-stone-100 text-stone-700 font-bold text-xs flex items-center justify-center shrink-0 font-mono">
                  {idx + 1}
                </span>
                {article.thumbnailUrl && (
                  <img
                    src={article.thumbnailUrl}
                    alt=""
                    className="w-12 h-9 rounded-lg object-cover border border-stone-200 shrink-0"
                  />
                )}
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-900">
                      {article.category}
                    </span>
                    <span className="text-[10px] text-stone-400 font-mono">
                      {new Date(article.publishedAt).toLocaleDateString('ja-JP')}
                    </span>
                  </div>
                  <h4 className="text-xs font-bold text-stone-900 truncate mt-0.5 max-w-xl">
                    {article.title}
                  </h4>
                </div>
              </div>

              <div className="flex items-center justify-end gap-3 shrink-0 text-xs">
                <div className="text-right">
                  <div className="font-mono font-bold text-stone-800">{article.pvCount.toLocaleString()} PV</div>
                  <div className="text-[10px] text-amber-700 font-bold">指数: {article.shirankedoIndex}点</div>
                </div>
                <a
                  href={`/article/${article.slug}`}
                  target="_blank"
                  rel="noreferrer"
                  className="p-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-stone-700 transition-colors"
                  title="サイトで確認"
                >
                  <Eye className="w-4 h-4" />
                </a>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 📚 全記事一覧・検索・管理 */}
      <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h3 className="text-sm sm:text-base font-black text-stone-900">
            全記事一覧・検索・編集
          </h3>

          {/* 検索 & フィルタ */}
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative">
              <Search className="w-3.5 h-3.5 text-stone-400 absolute left-3 top-2.5" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="記事検索..."
                className="pl-8 pr-3 py-1.5 rounded-xl border border-stone-300 text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 w-44"
              />
            </div>
            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              className="px-2.5 py-1.5 rounded-xl border border-stone-300 text-xs bg-white font-medium"
            >
              <option value="all">全カテゴリ</option>
              <option value="エンタメ・話題">エンタメ・話題</option>
              <option value="IT・ガジェット">IT・ガジェット</option>
              <option value="ビジネス・経済">ビジネス・経済</option>
              <option value="生活・雑学">生活・雑学</option>
              <option value="スポーツ">スポーツ</option>
            </select>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-2.5 py-1.5 rounded-xl border border-stone-300 text-xs bg-white font-medium"
            >
              <option value="all">全ステータス</option>
              <option value="published">公開中</option>
              <option value="on_hold">保留中</option>
              <option value="draft">下書き</option>
            </select>
          </div>
        </div>

        {/* 記事テーブル */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-stone-50 text-stone-600 font-bold border-y border-stone-200">
              <tr>
                <th className="py-2.5 px-3">記事</th>
                <th className="py-2.5 px-3">カテゴリ</th>
                <th className="py-2.5 px-3 text-center">指数</th>
                <th className="py-2.5 px-3 text-right">PV数</th>
                <th className="py-2.5 px-3">状態</th>
                <th className="py-2.5 px-3 text-right">操作</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-stone-100">
              {filteredArticles.map((article) => (
                <tr key={article.id} className="hover:bg-stone-50/80 transition-colors">
                  <td className="py-3 px-3 max-w-xs sm:max-w-md">
                    <div className="font-bold text-stone-900 truncate">{article.title}</div>
                    <div className="text-[10px] text-stone-400 font-mono mt-0.5">
                      {new Date(article.publishedAt).toLocaleString('ja-JP')}
                    </div>
                  </td>
                  <td className="py-3 px-3">
                    <span className="px-2 py-0.5 rounded bg-stone-100 text-stone-700 text-[10px] font-bold">
                      {article.category}
                    </span>
                  </td>
                  <td className="py-3 px-3 text-center font-mono font-bold text-amber-700">
                    {article.shirankedoIndex}点
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-stone-700">
                    {article.pvCount.toLocaleString()}
                  </td>
                  <td className="py-3 px-3">
                    <span
                      className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold ${
                        article.status === 'published'
                          ? 'bg-emerald-100 text-emerald-800'
                          : article.status === 'on_hold'
                          ? 'bg-rose-100 text-rose-800'
                          : 'bg-stone-100 text-stone-600'
                      }`}
                    >
                      {article.status === 'published'
                        ? '公開中'
                        : article.status === 'on_hold'
                        ? '保留中'
                        : '下書き'}
                    </span>
                  </td>
                  <td className="py-3 px-3 text-right space-x-1.5 whitespace-nowrap">
                    {article.status !== 'published' ? (
                      <button
                        type="button"
                        onClick={() => onUpdateStatus(article.id, 'published')}
                        className="px-2 py-1 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[11px] font-bold transition-colors cursor-pointer"
                      >
                        公開する
                      </button>
                    ) : (
                      <button
                        type="button"
                        onClick={() => onUpdateStatus(article.id, 'on_hold')}
                        className="px-2 py-1 rounded bg-stone-100 text-stone-700 hover:bg-stone-200 text-[11px] font-bold transition-colors cursor-pointer"
                      >
                        保留にする
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => onUpdateStatus(article.id, 'archived')}
                      className="p-1 rounded text-stone-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                      title="削除"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
