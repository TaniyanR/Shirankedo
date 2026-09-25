import React, { useState } from 'react';
import { DollarSign, Check, ExternalLink } from 'lucide-react';

export const AdsTab: React.FC = () => {
  const [headerBannerEnabled, setHeaderBannerEnabled] = useState(true);
  const [headerBannerUrl, setHeaderBannerUrl] = useState('https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&w=1200&q=80');
  const [headerBannerLink, setHeaderBannerLink] = useState('https://example.com/ad-sample');
  const [inArticleEnabled, setInArticleEnabled] = useState(true);
  const [savedMsg, setSavedMsg] = useState(false);

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    setSavedMsg(true);
    setTimeout(() => setSavedMsg(false), 3000);
  };

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「アフィリエイト・広告設定」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">アフィリエイト・広告設定</h2>
            <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
              💰 収益化稼働中
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            ヘッダーバナー広告、記事末尾アフィリエイトリンク、および提携ASPコードの管理を行います。
          </p>
        </div>
      </div>

      {savedMsg && (
        <div className="p-4 bg-emerald-50 border border-emerald-300 text-emerald-950 rounded-2xl text-xs font-bold flex items-center gap-2">
          <Check className="w-4 h-4 text-emerald-600" />
          <span>広告・アフィリエイト設定を保存しました！</span>
        </div>
      )}

      {/* 広告設定フォーム */}
      <form onSubmit={handleSave} className="bg-white rounded-3xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-6">
        {/* ヘッダーバナー広告 */}
        <div className="space-y-4 border-b border-stone-100 pb-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm sm:text-base font-black text-stone-900">ヘッダー最上部バナー広告枠</h3>
              <p className="text-xs text-stone-500">全ページの最上部および記事一覧の上に表示される目立つ横長バナーです。</p>
            </div>
            <button
              type="button"
              onClick={() => setHeaderBannerEnabled(!headerBannerEnabled)}
              className={`w-12 h-6 rounded-full transition-colors p-1 cursor-pointer flex items-center ${
                headerBannerEnabled ? 'bg-amber-500 justify-end' : 'bg-stone-300 justify-start'
              }`}
            >
              <div className="w-4 h-4 rounded-full bg-white shadow-xs" />
            </button>
          </div>

          {headerBannerEnabled && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-stone-700">バナー画像URL</label>
                <input
                  type="url"
                  value={headerBannerUrl}
                  onChange={(e) => setHeaderBannerUrl(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-stone-700">リンク先URL</label>
                <input
                  type="url"
                  value={headerBannerLink}
                  onChange={(e) => setHeaderBannerLink(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                />
              </div>
            </div>
          )}
        </div>

        {/* 記事中・記事末尾アフィリエイト */}
        <div className="space-y-4 border-b border-stone-100 pb-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm sm:text-base font-black text-stone-900">記事末尾・客観ファクト連動アフィリエイト</h3>
              <p className="text-xs text-stone-500">記事の結論オチ「知らんけど」の直下に配置される関連商品・サービス枠です。</p>
            </div>
            <button
              type="button"
              onClick={() => setInArticleEnabled(!inArticleEnabled)}
              className={`w-12 h-6 rounded-full transition-colors p-1 cursor-pointer flex items-center ${
                inArticleEnabled ? 'bg-amber-500 justify-end' : 'bg-stone-300 justify-start'
              }`}
            >
              <div className="w-4 h-4 rounded-full bg-white shadow-xs" />
            </button>
          </div>
        </div>

        <div className="flex justify-end pt-2">
          <button
            type="submit"
            className="px-6 py-2.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs transition-colors cursor-pointer shadow-xs"
          >
            広告設定を保存
          </button>
        </div>
      </form>
    </div>
  );
};
