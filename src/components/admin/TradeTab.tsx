import React, { useState } from 'react';
import { Link2, Plus, ExternalLink, RefreshCw, CheckCircle, Trash2 } from 'lucide-react';

export const TradeTab: React.FC = () => {
  const [partners, setPartners] = useState([
    { id: 1, name: '関西トレンドまとめ速報アンテナ', url: 'https://kansai-trend.example.com', rss: 'https://kansai-trend.example.com/rss.xml', status: 'active', inbound: 480, outbound: 310 },
    { id: 2, name: '爆速芸能ニュースアンテナ', url: 'https://geinou-news.example.com', rss: 'https://geinou-news.example.com/rss.xml', status: 'active', inbound: 320, outbound: 280 },
    { id: 3, name: 'しらんけど同盟ポータル', url: 'https://shirankedo-hub.example.com', rss: 'https://shirankedo-hub.example.com/feed', status: 'active', inbound: 190, outbound: 160 },
  ]);

  const [newName, setNewName] = useState('');
  const [newUrl, setNewUrl] = useState('');
  const [newRss, setNewRss] = useState('');
  const [pingSuccess, setPingSuccess] = useState<string | null>(null);

  const handleAdd = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim() || !newUrl.trim()) return;
    setPartners([
      ...partners,
      {
        id: Date.now(),
        name: newName,
        url: newUrl,
        rss: newRss || newUrl + '/rss.xml',
        status: 'active',
        inbound: 0,
        outbound: 0,
      },
    ]);
    setNewName('');
    setNewUrl('');
    setNewRss('');
  };

  const handlePingTest = (partnerName: string) => {
    setPingSuccess(`提携先「${partnerName}」へのPing送受信テストに成功しました！`);
    setTimeout(() => setPingSuccess(null), 3000);
  };

  const handleDelete = (id: number) => {
    setPartners(partners.filter((p) => p.id !== id));
  };

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「相互リンク・相互RSS提携」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">相互リンク・相互RSS提携</h2>
            <span className="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[11px] font-bold">
              {partners.length} サイト 提携稼働中
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            他サイトとの相互アクセス流入（IN/OUT）の最大化と自動RSSアンテナPingの管理を行います。
          </p>
        </div>
      </div>

      {pingSuccess && (
        <div className="p-4 bg-emerald-50 border border-emerald-300 text-emerald-950 rounded-2xl text-xs font-bold flex items-center gap-2">
          <CheckCircle className="w-4 h-4 text-emerald-600" />
          <span>{pingSuccess}</span>
        </div>
      )}

      {/* 新規提携サイト登録 */}
      <form onSubmit={handleAdd} className="bg-white rounded-3xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-4">
        <h3 className="text-sm sm:text-base font-black text-stone-900 flex items-center gap-2">
          <Plus className="w-4 h-4 text-blue-600" />
          <span>新規相互RSS・アンテナサイト提携の追加</span>
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-stone-700">提携先サイト名 *</label>
            <input
              type="text"
              required
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              placeholder="例: ○○アンテナ速報"
              className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
            />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-stone-700">サイトURL *</label>
            <input
              type="url"
              required
              value={newUrl}
              onChange={(e) => setNewUrl(e.target.value)}
              placeholder="https://example.com"
              className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
            />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-stone-700">RSSフィードURL</label>
            <input
              type="url"
              value={newRss}
              onChange={(e) => setNewRss(e.target.value)}
              placeholder="https://example.com/rss.xml"
              className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
            />
          </div>
        </div>

        <div className="flex justify-end pt-2">
          <button
            type="submit"
            className="px-5 py-2.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs transition-colors cursor-pointer shadow-xs"
          >
            提携先を登録する
          </button>
        </div>
      </form>

      {/* 提携先一覧テーブル */}
      <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm space-y-4">
        <h3 className="text-sm sm:text-base font-black text-stone-900">
          登録済 相互提携サイト一覧
        </h3>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-stone-50 text-stone-600 font-bold border-y border-stone-200">
              <tr>
                <th className="py-2.5 px-3">サイト名</th>
                <th className="py-2.5 px-3">流入(IN)</th>
                <th className="py-2.5 px-3">送客(OUT)</th>
                <th className="py-2.5 px-3">状態</th>
                <th className="py-2.5 px-3 text-right">操作</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-stone-100">
              {partners.map((p) => (
                <tr key={p.id} className="hover:bg-stone-50 transition-colors">
                  <td className="py-3 px-3">
                    <div className="font-bold text-stone-900">{p.name}</div>
                    <a
                      href={p.url}
                      target="_blank"
                      rel="noreferrer"
                      className="text-[11px] text-stone-400 hover:text-amber-600 flex items-center gap-1 mt-0.5"
                    >
                      <span>{p.url}</span>
                      <ExternalLink className="w-3 h-3" />
                    </a>
                  </td>
                  <td className="py-3 px-3 font-mono font-bold text-emerald-700">{p.inbound} PV</td>
                  <td className="py-3 px-3 font-mono font-bold text-blue-700">{p.outbound} PV</td>
                  <td className="py-3 px-3">
                    <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                      双方向疎通中
                    </span>
                  </td>
                  <td className="py-3 px-3 text-right space-x-2">
                    <button
                      type="button"
                      onClick={() => handlePingTest(p.name)}
                      className="px-2.5 py-1 rounded bg-stone-100 hover:bg-stone-200 text-stone-700 text-[11px] font-bold transition-colors cursor-pointer"
                    >
                      Ping送信
                    </button>
                    <button
                      type="button"
                      onClick={() => handleDelete(p.id)}
                      className="p-1 text-stone-400 hover:text-rose-600 cursor-pointer"
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
