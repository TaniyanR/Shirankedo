import React, { useEffect, useState } from 'react';
import { BarChart3, RefreshCw } from 'lucide-react';

type Period = 'today' | '7days' | '30days';

interface AnalyticsData {
  period?: string;
  summary?: {
    totalPv?: number;
    totalUu?: number;
    todayPv?: number;
    todayUu?: number;
    yesterdayPv?: number;
    yesterdayUu?: number;
  };
  dailyHistory?: Array<{ date: string; pv: number; uu: number }>;
  sources?: Array<{ name: string; pv?: number; uu?: number; share?: number }>;
  devices?: Record<string, number>;
  topArticles?: Array<{ id?: number; title: string; pv: number; uu?: number }>;
  trackingMode?: string;
}

export const AnalyticsTab: React.FC = () => {
  const [period, setPeriod] = useState<Period>('today');
  const [analyticsData, setAnalyticsData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const fetchAnalytics = () => {
    setLoading(true);
    setError('');
    fetch(`/api/analytics?period=${period}`)
      .then((res) => {
        if (!res.ok) throw new Error('アクセス解析データを取得できませんでした');
        return res.json();
      })
      .then((data) => setAnalyticsData(data || {}))
      .catch((err) => {
        console.error(err);
        setAnalyticsData(null);
        setError(err?.message || 'アクセス解析データを取得できませんでした');
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchAnalytics();
  }, [period]);

  const summary = analyticsData?.summary || {};
  const pv = Number(summary.totalPv || 0);
  const uu = Number(summary.totalUu || 0);
  const history = analyticsData?.dailyHistory || [];
  const sources = analyticsData?.sources || [];
  const topArticles = analyticsData?.topArticles || [];

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
        <h2 className="text-xl sm:text-2xl font-black text-stone-900 tracking-tight flex items-center gap-2">
          <span>📊</span><span>アクセス解析</span>
        </h2>
        <p className="text-xs text-stone-500 mt-1">
          実際に取得できたアクセスデータだけを表示します。ダミー値や推測値は表示しません。
        </p>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center bg-stone-100 p-1 rounded-xl border border-stone-200 text-xs font-bold">
          {([
            ['today', '1日'],
            ['7days', '7日'],
            ['30days', '30日'],
          ] as Array<[Period, string]>).map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setPeriod(value)}
              className={`px-3 py-1.5 rounded-lg transition-all ${
                period === value ? 'bg-amber-500 text-stone-950 shadow-xs' : 'text-stone-600 hover:text-stone-900'
              }`}
            >
              {label}
            </button>
          ))}
        </div>
        <button
          type="button"
          onClick={fetchAnalytics}
          disabled={loading}
          className="p-2 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700"
          title="再読み込み"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {error && (
        <div className="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
          <div className="text-xs font-bold text-stone-500">PV</div>
          <div className="text-3xl font-black text-stone-900 mt-2">{pv.toLocaleString()}</div>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
          <div className="text-xs font-bold text-stone-500">UU</div>
          <div className="text-3xl font-black text-amber-600 mt-2">{uu.toLocaleString()}</div>
        </div>
      </div>

      <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm space-y-4">
        <div className="flex items-center gap-2">
          <BarChart3 className="w-4 h-4 text-amber-600" />
          <h3 className="text-sm font-black text-stone-900">PV・UU推移</h3>
        </div>
        {history.length === 0 ? (
          <div className="py-10 text-center text-xs text-stone-400">まだ実測データがありません</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="text-stone-400 border-b border-stone-100">
                  <th className="py-2 text-left">日付</th>
                  <th className="py-2 text-right">PV</th>
                  <th className="py-2 text-right">UU</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {history.map((d, i) => (
                  <tr key={i}>
                    <td className="py-2.5">{d.date}</td>
                    <td className="py-2.5 text-right font-mono font-bold">{Number(d.pv || 0).toLocaleString()}</td>
                    <td className="py-2.5 text-right font-mono font-bold">{Number(d.uu || 0).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm">
          <h3 className="text-sm font-black text-stone-900 mb-4">参照元</h3>
          {sources.length === 0 ? (
            <div className="py-8 text-center text-xs text-stone-400">まだ実測データがありません</div>
          ) : (
            <div className="space-y-2">
              {sources.map((s, i) => (
                <div key={i} className="flex justify-between text-xs border-b border-stone-100 py-2">
                  <span className="font-bold text-stone-700">{s.name}</span>
                  <span className="font-mono">{Number(s.pv || 0).toLocaleString()} PV</span>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm">
          <h3 className="text-sm font-black text-stone-900 mb-4">人気記事</h3>
          {topArticles.length === 0 ? (
            <div className="py-8 text-center text-xs text-stone-400">まだ実測データがありません</div>
          ) : (
            <div className="space-y-2">
              {topArticles.map((a, i) => (
                <div key={i} className="flex items-start justify-between gap-4 text-xs border-b border-stone-100 py-2">
                  <span className="font-bold text-stone-700 line-clamp-2">{a.title}</span>
                  <span className="font-mono whitespace-nowrap">{Number(a.pv || 0).toLocaleString()} PV</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
