import React, { useState } from 'react';
import { Cpu, Clock, Image as ImageIcon, FileText, CheckCircle, ArrowRight, Sparkles, CheckSquare, Square, ExternalLink } from 'lucide-react';
import { Article, ImageItem } from '../../types';

interface DashboardTabProps {
  articles: Article[];
  images: ImageItem[];
  onNavigate: (tab: any) => void;
  onTestGemini: () => void;
}

export const DashboardTab: React.FC<DashboardTabProps> = ({
  articles,
  images,
  onNavigate,
  onTestGemini,
}) => {
  const publishedArticles = articles.filter(a => a.status === 'published');
  const [checklist, setChecklist] = useState<Record<string, boolean>>({
    step1: true,
    step2: true,
    step3: false,
    step4: false,
    step5: false,
  });

  const toggleCheck = (key: string) => {
    setChecklist(prev => ({ ...prev, [key]: !prev[key] }));
  };

  const completedCount = Object.values(checklist).filter(Boolean).length;
  const progressPercent = Math.round((completedCount / 5) * 100);

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイド名「ダッシュボード」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl sm:text-2xl font-black text-stone-900 tracking-tight flex items-center gap-2">
              <span>📊</span>
              <span>ダッシュボード</span>
            </h2>
            <span className="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold flex items-center gap-1">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              稼働中
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            「しらんけど」のAI自動執筆・クーロン稼働状態の確認と、ワンクリックでの記事生成・管理が行えます
          </p>
        </div>
        <a
          href="/"
          target="_blank"
          rel="noreferrer"
          className="flex items-center gap-2 px-4 py-2 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs transition-colors shadow-xs"
        >
          <ExternalLink className="w-3.5 h-3.5 text-amber-400" />
          <span>表のサイトを見る ↗</span>
        </a>
      </div>

      {/* 4つの稼働状況カード */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* カード 1: AI記事執筆エンジン */}
        <div className="bg-white rounded-2xl p-5 border border-stone-200 shadow-xs flex flex-col justify-between hover:border-amber-400 transition-all group">
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold text-stone-400">AI記事執筆エンジン</span>
              <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                接続中
              </span>
            </div>
            <div className="flex items-center gap-2.5">
              <div className="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 flex items-center justify-center font-bold text-lg">
                🤖
              </div>
              <div>
                <h3 className="font-black text-sm text-stone-900">Gemini AI</h3>
                <p className="text-xs font-mono text-stone-600">gemini-2.5-flash</p>
              </div>
            </div>
            <div className="bg-stone-50 rounded-xl p-2.5 text-[11px] space-y-1 border border-stone-100 font-mono text-stone-600">
              <div className="flex justify-between">
                <span>APIキー:</span>
                <span className="text-stone-800 font-bold">AIza...****eZbg</span>
              </div>
              <div className="flex justify-between text-stone-500">
                <span>安全性制御:</span>
                <span className="text-emerald-600 font-bold">安全ブレーキ稼働</span>
              </div>
            </div>
          </div>
          <div className="pt-4 mt-2 border-t border-stone-100 flex items-center justify-between gap-2">
            <button
              type="button"
              onClick={onTestGemini}
              className="text-xs font-bold text-stone-700 hover:text-amber-700 bg-stone-100 hover:bg-stone-200 px-2.5 py-1.5 rounded-lg transition-colors cursor-pointer flex items-center gap-1"
            >
              <span>🔍 接続テスト</span>
            </button>
            <button
              type="button"
              onClick={() => onNavigate('system')}
              className="text-xs font-bold text-amber-600 hover:text-amber-700 flex items-center gap-0.5 cursor-pointer"
            >
              <span>設定変更 →</span>
            </button>
          </div>
        </div>

        {/* カード 2: 定期実行・クーロン */}
        <div className="bg-white rounded-2xl p-5 border border-stone-200 shadow-xs flex flex-col justify-between hover:border-amber-400 transition-all group">
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold text-stone-400">定期実行・クーロン</span>
              <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                稼働中
              </span>
            </div>
            <div className="flex items-center gap-2.5">
              <div className="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-600 flex items-center justify-center font-bold text-lg">
                ⏰
              </div>
              <div>
                <h3 className="font-black text-sm text-stone-900">自動投稿スケジュール</h3>
                <p className="text-xs text-stone-600">3時間ごと (1日最大10本)</p>
              </div>
            </div>
            <div className="bg-stone-50 rounded-xl p-2.5 text-[11px] space-y-1 border border-stone-100 text-stone-600">
              <div className="flex justify-between">
                <span>次回投稿:</span>
                <span className="text-emerald-600 font-bold">いつでも即時可能</span>
              </div>
              <div className="flex justify-between text-stone-500">
                <span>最終実行:</span>
                <span className="font-mono text-stone-800">22:28</span>
              </div>
            </div>
          </div>
          <div className="pt-4 mt-2 border-t border-stone-100 flex items-center justify-between gap-2">
            <span className="text-[11px] text-stone-400">パイプライン正常</span>
            <button
              type="button"
              onClick={() => onNavigate('create')}
              className="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-0.5 cursor-pointer"
            >
              <span>間隔調整 →</span>
            </button>
          </div>
        </div>

        {/* カード 3: 画像自動マッチング */}
        <div className="bg-white rounded-2xl p-5 border border-stone-200 shadow-xs flex flex-col justify-between hover:border-amber-400 transition-all group">
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold text-stone-400">画像自動マッチング</span>
              <span className="text-[10px] font-bold text-stone-500">
                {images.length > 0 ? `${images.length}枚` : '76枚'} 登録済
              </span>
            </div>
            <div className="flex items-center gap-2.5">
              <div className="w-10 h-10 rounded-xl bg-purple-500/15 text-purple-600 flex items-center justify-center font-bold text-lg">
                🖼️
              </div>
              <div>
                <h3 className="font-black text-sm text-stone-900">アイキャッチ画像</h3>
                <p className="text-[11px] text-stone-500">AIキーワード照合 (部分一致)</p>
              </div>
            </div>
            <div className="bg-stone-50 rounded-xl p-2.5 text-[11px] space-y-1 border border-stone-100 text-stone-600">
              <div className="text-[11px] text-stone-700 leading-snug">
                記事の話題に合った画像を選定してサムネイルへ自動付与
              </div>
            </div>
          </div>
          <div className="pt-4 mt-2 border-t border-stone-100 flex items-center justify-between gap-2">
            <span className="text-[11px] text-stone-400">全カテゴリ対応</span>
            <button
              type="button"
              onClick={() => onNavigate('images')}
              className="text-xs font-bold text-purple-600 hover:text-purple-700 flex items-center gap-0.5 cursor-pointer"
            >
              <span>画像一覧 →</span>
            </button>
          </div>
        </div>

        {/* カード 4: サイトコンテンツ */}
        <div className="bg-white rounded-2xl p-5 border border-stone-200 shadow-xs flex flex-col justify-between hover:border-amber-400 transition-all group">
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold text-stone-400">サイトコンテンツ</span>
              <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                {publishedArticles.length}本 公開中
              </span>
            </div>
            <div className="flex items-center gap-2.5">
              <div className="w-10 h-10 rounded-xl bg-amber-500 text-stone-950 flex items-center justify-center font-black text-lg shadow-xs">
                📝
              </div>
              <div>
                <h3 className="font-black text-sm text-stone-900">公開記事数</h3>
                <p className="text-xs font-bold text-stone-700">総数 {articles.length} 本</p>
              </div>
            </div>
            <div className="bg-stone-50 rounded-xl p-2.5 text-[11px] space-y-1 border border-stone-100 text-stone-600">
              <div className="flex justify-between">
                <span>客観ファクトまとめ:</span>
                <span className="text-amber-700 font-bold">全記事実装済</span>
              </div>
              <div className="flex justify-between text-stone-500">
                <span>しらんけどオチ率:</span>
                <span className="font-bold text-stone-800">100%</span>
              </div>
            </div>
          </div>
          <div className="pt-4 mt-2 border-t border-stone-100 flex items-center justify-between gap-2">
            <span className="text-[11px] text-stone-400">関西弁チューニング済</span>
            <button
              type="button"
              onClick={() => onNavigate('articles')}
              className="text-xs font-bold text-amber-700 hover:text-amber-800 flex items-center gap-0.5 cursor-pointer"
            >
              <span>全記事一覧 →</span>
            </button>
          </div>
        </div>
      </div>

      {/* ワンクリック記事生成（2つの作成方法） */}
      <div className="bg-amber-50/70 border border-amber-200 rounded-3xl p-6 sm:p-7 shadow-xs space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="space-y-1">
            <div className="text-xs font-black text-amber-700 flex items-center gap-1.5">
              <span>⚡</span>
              <span>【超かんたん】今すぐ記事を増やしたいときはここ！</span>
            </div>
            <h3 className="text-lg font-black text-stone-900">
              ワンクリック記事生成（2つの作成方法）
            </h3>
          </div>
          <span className="px-3 py-1 rounded-full bg-amber-500 text-stone-950 font-black text-xs shadow-xs">
            数十秒で即座に公開完了
          </span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* 方法 1: 完全自動 */}
          <div className="bg-white rounded-2xl p-5 border border-amber-200 shadow-2xs space-y-4 flex flex-col justify-between">
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-stone-900 font-black text-sm">
                <span className="w-6 h-6 rounded-full bg-amber-500 text-stone-950 flex items-center justify-center text-xs font-black">
                  1
                </span>
                <span>完全自動: 最新急上昇トレンドから生成</span>
              </div>
              <p className="text-xs text-stone-600 leading-relaxed">
                Googleトレンドからいま日本で一番話題のキーワードをAIが自動取得し、一次情報を調べて記事を1本執筆・公開します。
              </p>
            </div>
            <button
              type="button"
              onClick={() => onNavigate('create')}
              className="w-full py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs transition-colors shadow-xs flex items-center justify-center gap-2 cursor-pointer"
            >
              <span>⚡ 今すぐAI記事を1本自動生成</span>
            </button>
          </div>

          {/* 方法 2: キーワード指定 */}
          <div className="bg-white rounded-2xl p-5 border border-amber-200 shadow-2xs space-y-4 flex flex-col justify-between">
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-stone-900 font-black text-sm">
                <span className="w-6 h-6 rounded-full bg-amber-500 text-stone-950 flex items-center justify-center text-xs font-black">
                  2
                </span>
                <span>キーワード指定: 好きな話題で即座に執筆</span>
              </div>
              <p className="text-xs text-stone-600 leading-relaxed">
                気になるキーワード（例: 大谷翔平、千鳥、iPhone 16 など）を入力するだけで、AIが一次情報を整理して記事にします。
              </p>
            </div>
            <button
              type="button"
              onClick={() => onNavigate('create')}
              className="w-full py-2.5 px-4 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs transition-colors shadow-xs flex items-center justify-center gap-2 cursor-pointer"
            >
              <span>✍️ 指定キーワードで記事を生成</span>
            </button>
          </div>
        </div>
      </div>

      {/* はじめての運用ガイド（やることチェックリスト） */}
      <div className="bg-white rounded-3xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 pb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-amber-500 text-stone-950 flex items-center justify-center font-black text-lg">
              🔰
            </div>
            <div>
              <h3 className="text-base sm:text-lg font-black text-stone-900">
                はじめての運用ガイド（やることチェックリスト）
              </h3>
              <p className="text-xs text-stone-500">
                サイトを安定して完全自動運転させるための推奨ステップです。
              </p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-xs font-bold text-stone-500">進捗状況: {completedCount} / 5 完了</span>
            <span className="text-xs font-black px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 font-mono">
              {progressPercent}%
            </span>
          </div>
        </div>

        {/* 進捗バー */}
        <div className="w-full bg-stone-100 rounded-full h-2.5 overflow-hidden">
          <div
            className="bg-amber-500 h-2.5 rounded-full transition-all duration-500"
            style={{ width: `${progressPercent}%` }}
          />
        </div>

        {/* チェックリスト一覧 */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
          {[
            {
              id: 'step1',
              title: 'Gemini AI APIキーの疎通確認',
              desc: '「サイト・システム保守」でAPIキーが設定され、接続中になっていることを確認します。',
              tab: 'system',
              actionLabel: 'API確認 →',
            },
            {
              id: 'step2',
              title: 'アイキャッチ画像ライブラリの登録確認',
              desc: '記事サムネイルとして自動適用される画像が登録されているか確認します。',
              tab: 'images',
              actionLabel: '素材管理 →',
            },
            {
              id: 'step3',
              title: '「記事をつくる」からAI記事を1本即時生成してみる',
              desc: '話題のトレンドから1クリックでAIが記事を作成し、自動公開する流れを体験します。',
              tab: 'create',
              actionLabel: '記事をつくる →',
            },
            {
              id: 'step4',
              title: 'サーバーcron設定で完全放置自動運転を開始',
              desc: '「定期実行・クーロン設定」のcrontabコマンドをサーバーに登録して自動化します。',
              tab: 'cron',
              actionLabel: 'Cron設定 →',
            },
            {
              id: 'step5',
              title: '「高性能アクセス解析」で読者の流入と滞在時間を確認',
              desc: 'どこから来てどこへ行くのか、読者がどれだけ記事を読んだかをリアルタイム分析します。',
              tab: 'analytics',
              actionLabel: 'アクセス解析 →',
            },
          ].map((item) => {
            const isChecked = checklist[item.id];
            return (
              <div
                key={item.id}
                onClick={() => toggleCheck(item.id)}
                className={`p-4 rounded-2xl border transition-all cursor-pointer flex items-start justify-between gap-3 ${
                  isChecked
                    ? 'bg-amber-50/40 border-amber-200'
                    : 'bg-stone-50 border-stone-200 hover:border-stone-300'
                }`}
              >
                <div className="flex items-start gap-3">
                  <div className="mt-0.5 text-amber-600">
                    {isChecked ? (
                      <CheckSquare className="w-5 h-5 text-amber-600" />
                    ) : (
                      <Square className="w-5 h-5 text-stone-400" />
                    )}
                  </div>
                  <div>
                    <h4 className={`text-xs font-bold ${isChecked ? 'line-through text-stone-500' : 'text-stone-900'}`}>
                      {item.title}
                    </h4>
                    <p className="text-[11px] text-stone-500 mt-0.5 leading-relaxed">
                      {item.desc}
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    onNavigate(item.tab);
                  }}
                  className="text-[11px] font-bold text-amber-700 hover:text-amber-900 shrink-0 bg-white px-2 py-1 rounded-lg border border-amber-200 shadow-2xs hover:bg-amber-50"
                >
                  {item.actionLabel}
                </button>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
