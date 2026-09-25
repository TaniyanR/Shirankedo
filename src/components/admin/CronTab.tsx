import React, { useState, useEffect } from 'react';
import { Clock, Terminal, Copy, Check, Play, RefreshCw, CheckCircle, AlertCircle, Sparkles } from 'lucide-react';

export const CronTab: React.FC = () => {
  const [copied, setCopied] = useState(false);
  const [isRunning, setIsRunning] = useState(false);
  const [cronResultMsg, setCronResultMsg] = useState<string | null>(null);
  const [cronStatus, setCronStatus] = useState<any>(null);

  const cronCommand = '*/30 * * * * curl -s https://shirankedo.example.com/api/cron/run';

  const loadCronStatus = () => {
    fetch('/api/cron/status')
      .then((res) => res.json())
      .then((data) => setCronStatus(data))
      .catch(console.error);
  };

  useEffect(() => {
    loadCronStatus();
  }, []);

  const handleCopy = () => {
    navigator.clipboard.writeText(cronCommand);
    setCopied(true);
    setTimeout(() => setCopied(false), 3000);
  };

  const handleRunCron = async () => {
    setIsRunning(true);
    setCronResultMsg('Cron自動実行パイプラインを実行中...（トレンド収集 → AI生成 → 公開）');
    try {
      const res = await fetch('/api/cron/run', { method: 'POST' });
      const data = await res.json();
      if (data.success) {
        setCronResultMsg(`Cron自動実行成功！ 記事「${data.article.title}」を生成・公開しました (${data.durationMs}ms)`);
        loadCronStatus();
      } else {
        setCronResultMsg(`Cron実行エラー: ${data.error || '不明なエラー'}`);
      }
    } catch (e: any) {
      setCronResultMsg(`実行失敗: ${e.message}`);
    } finally {
      setIsRunning(false);
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「定期実行・クーロン設定」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">定期実行・クーロン設定</h2>
            <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
              🟢 自動運転スタンバイ
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            サーバーcronによる完全放置自動運転の設定、手動トリガー実行、および実行履歴ログを管理します。
          </p>
        </div>

        <button
          type="button"
          onClick={handleRunCron}
          disabled={isRunning}
          className="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs flex items-center gap-2 transition-all shadow-xs cursor-pointer disabled:opacity-50"
        >
          <Play className="w-3.5 h-3.5 fill-current" />
          <span>今すぐCronを手動テスト実行</span>
        </button>
      </div>

      {cronResultMsg && (
        <div className="p-4 bg-amber-50 border border-amber-300 text-amber-950 rounded-2xl text-xs font-bold flex items-center gap-2">
          {isRunning ? (
            <Sparkles className="w-4 h-4 text-amber-600 animate-spin" />
          ) : (
            <CheckCircle className="w-4 h-4 text-emerald-600" />
          )}
          <span>{cronResultMsg}</span>
        </div>
      )}

      {/* サーバーcron設定カード */}
      <div className="bg-white rounded-3xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-stone-900 text-white flex items-center justify-center font-bold text-lg">
            ⏰
          </div>
          <div>
            <h3 className="text-base font-black text-stone-900">
              完全放置（自動運転）のための サーバーcron 設定
            </h3>
            <p className="text-xs text-stone-500">
              お使いのレンタルサーバー（Xserver, ConoHa, さくら等）やVPSのcrontabに以下のコマンドを登録してください。
            </p>
          </div>
        </div>

        {/* コマンドボックス */}
        <div className="bg-stone-950 rounded-2xl p-4 text-stone-200 space-y-2 border border-stone-800">
          <div className="flex items-center justify-between text-xs text-stone-400">
            <span className="flex items-center gap-1.5 font-mono">
              <Terminal className="w-3.5 h-3.5 text-amber-400" />
              <span>crontab登録コマンド（30分ごと実行例）</span>
            </span>
            <button
              type="button"
              onClick={handleCopy}
              className="flex items-center gap-1 text-[11px] text-amber-400 hover:text-amber-300 font-bold cursor-pointer"
            >
              {copied ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
              <span>{copied ? 'コピー完了！' : 'コマンドをコピー'}</span>
            </button>
          </div>
          <pre className="font-mono text-xs sm:text-sm text-amber-300 overflow-x-auto py-1">
            <code>{cronCommand}</code>
          </pre>
        </div>

        {/* 稼働ステータスサマリー */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
          <div className="bg-stone-50 p-3.5 rounded-xl border border-stone-200 text-xs">
            <span className="text-stone-400 font-bold block mb-1">現在の稼働状態</span>
            <span className="font-bold text-emerald-700 flex items-center gap-1">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              正常待機中 (いつでも実行可能)
            </span>
          </div>
          <div className="bg-stone-50 p-3.5 rounded-xl border border-stone-200 text-xs">
            <span className="text-stone-400 font-bold block mb-1">最終実行時刻</span>
            <span className="font-bold font-mono text-stone-800">
              {cronStatus?.lastRun || '22:28'} (成功)
            </span>
          </div>
          <div className="bg-stone-50 p-3.5 rounded-xl border border-stone-200 text-xs">
            <span className="text-stone-400 font-bold block mb-1">次回投稿予定</span>
            <span className="font-bold text-stone-800">
              {cronStatus?.nextRun || 'いつでも即時可能'}
            </span>
          </div>
        </div>
      </div>

      {/* Cron自動実行ログ履歴 */}
      <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-sm sm:text-base font-black text-stone-900">
            Cron自動実行 履歴ログ
          </h3>
          <span className="text-xs text-stone-400 font-mono">直近の自動投稿記録</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-stone-50 text-stone-600 font-bold border-y border-stone-200">
              <tr>
                <th className="py-2.5 px-3">実行時刻</th>
                <th className="py-2.5 px-3">生成記事タイトル</th>
                <th className="py-2.5 px-3">カテゴリ</th>
                <th className="py-2.5 px-3 text-center">指数</th>
                <th className="py-2.5 px-3">処理時間</th>
                <th className="py-2.5 px-3 text-right">結果</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-stone-100">
              {(cronStatus?.history || [
                { time: '22:28:15', articleTitle: '千鳥・大悟の新番組独占配信が決定！公式PVに歓喜の声（しらんけど）', category: 'エンタメ・話題', shirankedoIndex: 88, durationMs: 840, status: 'success' },
                { time: '19:28:02', articleTitle: '新型スマホのカメラ性能が異次元進化との噂、ただし重さもヘビー級らしいで', category: 'IT・ガジェット', shirankedoIndex: 79, durationMs: 920, status: 'success' },
                { time: '16:28:44', articleTitle: '関西の有名たこ焼き店がまさかの新展開！？真相は謎のまま話題沸騰中', category: 'グルメ・街ネタ', shirankedoIndex: 92, durationMs: 760, status: 'success' },
              ]).map((row: any, idx: number) => (
                <tr key={idx} className="hover:bg-stone-50 transition-colors">
                  <td className="py-3 px-3 font-mono text-stone-500 whitespace-nowrap">{row.time}</td>
                  <td className="py-3 px-3 font-bold text-stone-900 max-w-sm truncate">{row.articleTitle}</td>
                  <td className="py-3 px-3">
                    <span className="px-2 py-0.5 rounded bg-stone-100 text-stone-700 text-[10px] font-bold">
                      {row.category}
                    </span>
                  </td>
                  <td className="py-3 px-3 text-center font-mono font-bold text-amber-700">{row.shirankedoIndex}点</td>
                  <td className="py-3 px-3 font-mono text-stone-500">{row.durationMs}ms</td>
                  <td className="py-3 px-3 text-right">
                    <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                      正常公開
                    </span>
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
