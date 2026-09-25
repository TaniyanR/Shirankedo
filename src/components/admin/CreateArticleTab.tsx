import React, { useState } from 'react';
import { Zap, Sparkles, PenTool, Clock, Plus, CheckCircle, Flame, Sliders, Check } from 'lucide-react';
import { TrendCandidate } from '../../types';

interface CreateArticleTabProps {
  trends: TrendCandidate[];
  isGenerating: boolean;
  genStatusMsg: string | null;
  onGenerateArticle: (trendId?: number, keyword?: string) => void;
  onManualSubmit: (articleData: {
    title: string;
    category: string;
    body: string;
    conclusion: string;
    index: number;
    imageUrl?: string;
    status: 'published' | 'draft';
  }) => Promise<void>;
  settings: any;
  onSaveSettings: (newSettings: any) => Promise<void>;
}

export const CreateArticleTab: React.FC<CreateArticleTabProps> = ({
  trends,
  isGenerating,
  genStatusMsg,
  onGenerateArticle,
  onManualSubmit,
  settings,
  onSaveSettings,
}) => {
  const [subTab, setSubTab] = useState<'ai' | 'manual' | 'schedule'>('ai');
  const [customKeyword, setCustomKeyword] = useState('');

  // Manual Form State
  const [manualTitle, setManualTitle] = useState('');
  const [manualCategory, setManualCategory] = useState('エンタメ・話題');
  const [manualBody, setManualBody] = useState('');
  const [manualConclusion, setManualConclusion] = useState('…まあ、真相は知らんけどな！');
  const [manualIndex, setManualIndex] = useState(85);
  const [manualImageUrl, setManualImageUrl] = useState('');
  const [isSubmittingManual, setIsSubmittingManual] = useState(false);
  const [manualSuccessMsg, setManualSuccessMsg] = useState<string | null>(null);

  // Schedule State
  const [cronInterval, setCronInterval] = useState(settings?.cronIntervalHours || 3);
  const [maxPerDay, setMaxPerDay] = useState(settings?.maxArticlesPerDay || 10);
  const [startHour, setStartHour] = useState(7);
  const [endHour, setEndHour] = useState(24);
  const [autoActive, setAutoActive] = useState(settings?.autoGenerateEnabled ?? true);
  const [scheduleSavedMsg, setScheduleSavedMsg] = useState<string | null>(null);

  const handleManualSubmitInternal = async (status: 'published' | 'draft') => {
    if (!manualTitle.trim() || !manualBody.trim()) {
      alert('タイトルと本文を入力してください');
      return;
    }
    setIsSubmittingManual(true);
    try {
      await onManualSubmit({
        title: manualTitle,
        category: manualCategory,
        body: manualBody,
        conclusion: manualConclusion,
        index: manualIndex,
        imageUrl: manualImageUrl || undefined,
        status,
      });
      setManualSuccessMsg(`記事「${manualTitle}」を${status === 'published' ? '即時公開' : '下書き保存'}しました！`);
      setManualTitle('');
      setManualBody('');
      setTimeout(() => setManualSuccessMsg(null), 5000);
    } catch (e: any) {
      alert('記事の作成に失敗しました: ' + e.message);
    } finally {
      setIsSubmittingManual(false);
    }
  };

  const handleSaveScheduleInternal = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await onSaveSettings({
        ...settings,
        cronIntervalHours: cronInterval,
        maxArticlesPerDay: maxPerDay,
        autoGenerateEnabled: autoActive,
      });
      setScheduleSavedMsg('自動投稿スケジュールと時間帯コントロール設定を保存しました！');
      setTimeout(() => setScheduleSavedMsg(null), 4000);
    } catch (e: any) {
      alert('設定保存に失敗しました: ' + e.message);
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「記事をつくる (AI・手動)」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">記事をつくる (AI・手動)</h2>
            <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 text-[11px] font-bold">
              AI即時・手動投稿・自動スケジュール
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            ワンクリックでの即時AI生成、自由キーワード執筆、手動投稿エディタ、および自動投稿スケジュール設定を管理します。
          </p>
        </div>

        {/* サブ切り替えタブ */}
        <div className="flex items-center bg-stone-100 p-1 rounded-xl border border-stone-200 text-xs font-bold">
          <button
            type="button"
            onClick={() => setSubTab('ai')}
            className={`px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer ${
              subTab === 'ai' ? 'bg-amber-500 text-stone-950 shadow-xs' : 'text-stone-600 hover:text-stone-900'
            }`}
          >
            <Zap className="w-3.5 h-3.5" />
            <span>⚡ AI即時生成</span>
          </button>
          <button
            type="button"
            onClick={() => setSubTab('manual')}
            className={`px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer ${
              subTab === 'manual' ? 'bg-amber-500 text-stone-950 shadow-xs' : 'text-stone-600 hover:text-stone-900'
            }`}
          >
            <PenTool className="w-3.5 h-3.5" />
            <span>✍️ 手動投稿</span>
          </button>
          <button
            type="button"
            onClick={() => setSubTab('schedule')}
            className={`px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer ${
              subTab === 'schedule' ? 'bg-amber-500 text-stone-950 shadow-xs' : 'text-stone-600 hover:text-stone-900'
            }`}
          >
            <Clock className="w-3.5 h-3.5" />
            <span>⏰ 投稿スケジュール</span>
          </button>
        </div>
      </div>

      {/* ステータスメッセージ */}
      {genStatusMsg && (
        <div className="p-4 bg-amber-50 border border-amber-300 text-amber-950 rounded-2xl text-xs font-bold flex items-center gap-2">
          <Sparkles className="w-4 h-4 text-amber-600 animate-spin" />
          <span>{genStatusMsg}</span>
        </div>
      )}

      {/* SUB-TAB 1: ⚡ AI即時生成 */}
      {subTab === 'ai' && (
        <div className="space-y-6">
          {/* メインヒーロー: 今すぐAI記事を1本自動生成ボタン */}
          <div className="relative overflow-hidden bg-gradient-to-br from-stone-900 via-stone-850 to-stone-900 rounded-3xl p-6 sm:p-8 text-stone-100 border border-stone-800 shadow-xl">
            <div className="absolute top-0 right-0 -mt-6 -mr-6 w-56 h-56 bg-amber-500/15 rounded-full blur-3xl pointer-events-none" />
            
            <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
              <div className="space-y-3 max-w-xl">
                <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-400/20 text-amber-300 border border-amber-400/30 text-[11px] font-bold">
                  <Sparkles className="w-3.5 h-3.5 text-amber-400" />
                  <span>Gemini AI 高速トレンド執筆エンジン</span>
                </div>
                <h3 className="text-xl sm:text-2xl font-black text-white tracking-tight">
                  話題の最新トレンドから、関西弁の客観ファクト記事を即時生成
                </h3>
                <p className="text-xs sm:text-sm text-stone-300 leading-relaxed">
                  検索急上昇・SNS話題ワードを自動選定し、信頼できる客観ファクトを確認。「…知らんけど。」のオチをつけてアイキャッチ画像を自動マッチングし、一瞬で公開記事として仕上げます。
                </p>
              </div>

              {/* ユーザー指定の重要ボタン: 今すぐAI記事を1本自動生成 */}
              <div className="shrink-0 flex flex-col items-center gap-2">
                <button
                  type="button"
                  onClick={() => onGenerateArticle(undefined, undefined)}
                  disabled={isGenerating}
                  className="w-full md:w-auto px-6 py-4 rounded-2xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm transition-all transform active:scale-95 shadow-xl hover:shadow-amber-500/20 flex items-center justify-center gap-3 cursor-pointer disabled:opacity-50"
                >
                  <Zap className="w-5 h-5 fill-current text-stone-950 animate-pulse" />
                  <span>今すぐAI記事を1本自動生成</span>
                </button>
                <span className="text-[10px] text-stone-400 font-mono">
                  {isGenerating ? 'Gemini 2.5 Flashでリサーチ中...' : '即時実行・最短3秒で自動公開'}
                </span>
              </div>
            </div>
          </div>

          {/* 自由キーワード指定による即時執筆 */}
          <div className="bg-white rounded-2xl p-6 border border-stone-200 shadow-sm space-y-4">
            <div className="flex items-center gap-2">
              <span className="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 font-black text-xs flex items-center justify-center">
                🎯
              </span>
              <div>
                <h4 className="text-sm font-black text-stone-900">自由キーワード指定でAI記事を即時執筆</h4>
                <p className="text-xs text-stone-500">好きなテーマや気になるキーワードを入力してAIに書かせることができます。</p>
              </div>
            </div>

            <div className="flex flex-col sm:flex-row gap-2.5">
              <input
                type="text"
                value={customKeyword}
                onChange={(e) => setCustomKeyword(e.target.value)}
                placeholder="例: 最新AIツール、千鳥新番組、大阪万博パビリオン、ビットコイン急騰"
                className="flex-1 px-4 py-3 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
              />
              <button
                type="button"
                onClick={() => {
                  if (!customKeyword.trim()) return alert('キーワードを入力してください');
                  onGenerateArticle(undefined, customKeyword);
                  setCustomKeyword('');
                }}
                disabled={isGenerating || !customKeyword.trim()}
                className="px-5 py-3 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs flex items-center justify-center gap-2 transition-colors disabled:opacity-50 cursor-pointer shrink-0"
              >
                <Sparkles className="w-4 h-4 text-amber-400" />
                <span>このキーワードでAI記事を生成</span>
              </button>
            </div>

            {/* 急上昇トレンドチップ */}
            <div className="pt-2">
              <div className="text-[11px] font-bold text-stone-400 flex items-center gap-1.5 mb-2">
                <Flame className="w-3.5 h-3.5 text-amber-500" />
                <span>最新の急上昇トレンド候補からワンクリックで生成:</span>
              </div>
              <div className="flex flex-wrap gap-2">
                {trends.slice(0, 8).map((t) => (
                  <button
                    key={t.id}
                    type="button"
                    onClick={() => onGenerateArticle(t.id, t.keyword)}
                    disabled={isGenerating}
                    className="px-3 py-1.5 rounded-xl bg-stone-50 hover:bg-amber-50 border border-stone-200 hover:border-amber-300 text-stone-700 hover:text-amber-900 text-xs font-medium transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs"
                  >
                    <span>{t.keyword}</span>
                    <span className="text-[10px] text-amber-600 font-mono font-bold">
                      {t.velocityScore}pt
                    </span>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* SUB-TAB 2: ✍️ 手動投稿エディタ */}
      {subTab === 'manual' && (
        <div className="bg-white rounded-3xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-6">
          <div>
            <h3 className="text-base font-black text-stone-900 flex items-center gap-2">
              <PenTool className="w-5 h-5 text-amber-600" />
              <span>記事の新規作成（完全手動投稿）</span>
            </h3>
            <p className="text-xs text-stone-500 mt-1">
              AIを使わず、自ら独自取材・執筆した記事を手動で作成・即時公開できます。
            </p>
          </div>

          {manualSuccessMsg && (
            <div className="p-4 bg-emerald-50 border border-emerald-300 text-emerald-950 rounded-2xl text-xs font-bold flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-emerald-600" />
              <span>{manualSuccessMsg}</span>
            </div>
          )}

          <div className="space-y-4">
            {/* タイトル & カテゴリ */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="md:col-span-2 space-y-1.5">
                <label className="text-xs font-bold text-stone-700">記事タイトル *</label>
                <input
                  type="text"
                  value={manualTitle}
                  onChange={(e) => setManualTitle(e.target.value)}
                  placeholder="読者を惹きつける魅力的なタイトルを入力（例: 話題の〇〇を徹底調査！）"
                  className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-stone-700">カテゴリ *</label>
                <select
                  value={manualCategory}
                  onChange={(e) => setManualCategory(e.target.value)}
                  className="w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white"
                >
                  <option value="エンタメ・話題">エンタメ・話題</option>
                  <option value="IT・ガジェット">IT・ガジェット</option>
                  <option value="ビジネス・経済">ビジネス・経済</option>
                  <option value="生活・雑学">生活・雑学</option>
                  <option value="スポーツ">スポーツ</option>
                  <option value="グルメ・街ネタ">グルメ・街ネタ</option>
                </select>
              </div>
            </div>

            {/* 本文エディタ */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-bold text-stone-700">記事本文 *</label>
                {/* 関西弁ワンクリック挿入ボタン */}
                <div className="flex items-center gap-1.5">
                  <span className="text-[10px] text-stone-400 font-bold">関西弁挿入:</span>
                  {['…知らんけど。', 'ホンマかいな。', 'せやから言うたやん。'].map((phrase) => (
                    <button
                      key={phrase}
                      type="button"
                      onClick={() => setManualBody((prev) => prev + (prev.endsWith('\n') ? '' : ' ') + phrase)}
                      className="px-2 py-0.5 rounded bg-amber-100 hover:bg-amber-200 text-amber-900 text-[10px] font-bold cursor-pointer transition-colors"
                    >
                      +{phrase}
                    </button>
                  ))}
                </div>
              </div>
              <textarea
                value={manualBody}
                onChange={(e) => setManualBody(e.target.value)}
                rows={8}
                placeholder="記事の本文を入力してください。段落ごとに空行を挟むと読みやすくなります。"
                className="w-full px-3.5 py-3 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none leading-relaxed"
              />
            </div>

            {/* 結びのオチ文 & しらんけど指数 */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-stone-700">結びの一言（オチ）</label>
                <input
                  type="text"
                  value={manualConclusion}
                  onChange={(e) => setManualConclusion(e.target.value)}
                  placeholder="…まあ、真相は知らんけどな！"
                  className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none font-bold text-amber-900"
                />
              </div>
              <div className="space-y-1.5">
                <div className="flex justify-between items-center">
                  <label className="text-xs font-bold text-stone-700">しらんけど指数</label>
                  <span className="text-xs font-black text-amber-600 font-mono">{manualIndex} 点</span>
                </div>
                <input
                  type="range"
                  min="0"
                  max="100"
                  value={manualIndex}
                  onChange={(e) => setManualIndex(Number(e.target.value))}
                  className="w-full accent-amber-500"
                />
              </div>
            </div>

            {/* サムネイル画像URL */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-stone-700">アイキャッチ画像URL (任意)</label>
              <input
                type="url"
                value={manualImageUrl}
                onChange={(e) => setManualImageUrl(e.target.value)}
                placeholder="https://images.unsplash.com/... (空欄の場合はカテゴリから自動割当)"
                className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
              />
            </div>

            {/* アクションボタン */}
            <div className="pt-3 border-t border-stone-100 flex items-center justify-end gap-3">
              <button
                type="button"
                onClick={() => handleManualSubmitInternal('draft')}
                disabled={isSubmittingManual}
                className="px-4 py-2.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs transition-colors cursor-pointer disabled:opacity-50"
              >
                下書き保存
              </button>
              <button
                type="button"
                onClick={() => handleManualSubmitInternal('published')}
                disabled={isSubmittingManual}
                className="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs transition-all shadow-xs cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
              >
                <Plus className="w-4 h-4" />
                <span>今すぐ公開する</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* SUB-TAB 3: ⏰ 自動投稿スケジュール・時間帯コントロール */}
      {subTab === 'schedule' && (
        <form onSubmit={handleSaveScheduleInternal} className="bg-white rounded-3xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-6">
          <div className="flex items-center justify-between border-b border-stone-100 pb-4">
            <div>
              <h3 className="text-base font-black text-stone-900 flex items-center gap-2">
                <Clock className="w-5 h-5 text-blue-600" />
                <span>自動投稿スケジュール・時間帯コントロール</span>
              </h3>
              <p className="text-xs text-stone-500 mt-1">
                定期投稿の実行間隔、1日あたりの投稿数上限、深夜休止時間帯を設定できます。
              </p>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-stone-600">全自動運転:</span>
              <button
                type="button"
                onClick={() => setAutoActive(!autoActive)}
                className={`w-12 h-6 rounded-full transition-colors p-1 cursor-pointer flex items-center ${
                  autoActive ? 'bg-emerald-500 justify-end' : 'bg-stone-300 justify-start'
                }`}
              >
                <div className="w-4 h-4 rounded-full bg-white shadow-xs" />
              </button>
            </div>
          </div>

          {scheduleSavedMsg && (
            <div className="p-4 bg-emerald-50 border border-emerald-300 text-emerald-950 rounded-2xl text-xs font-bold flex items-center gap-2">
              <Check className="w-4 h-4 text-emerald-600" />
              <span>{scheduleSavedMsg}</span>
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* 投稿間隔 */}
            <div className="bg-stone-50 p-4 rounded-2xl border border-stone-200 space-y-2">
              <label className="text-xs font-bold text-stone-800">投稿間隔 (Cron実行頻度)</label>
              <select
                value={cronInterval}
                onChange={(e) => setCronInterval(Number(e.target.value))}
                className="w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs bg-white font-bold"
              >
                <option value={1}>1時間ごと (高頻度速報モード)</option>
                <option value={2}>2時間ごと</option>
                <option value={3}>3時間ごと (推奨・標準モード)</option>
                <option value={6}>6時間ごと</option>
                <option value={12}>12時間ごと (1日2回)</option>
              </select>
              <p className="text-[11px] text-stone-500">
                指定した間隔ごとに新しい話題トレンドを自動取得して記事を執筆します。
              </p>
            </div>

            {/* 1日あたりの最大投稿本数 */}
            <div className="bg-stone-50 p-4 rounded-2xl border border-stone-200 space-y-2">
              <label className="text-xs font-bold text-stone-800">1日あたりの最大投稿本数</label>
              <select
                value={maxPerDay}
                onChange={(e) => setMaxPerDay(Number(e.target.value))}
                className="w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs bg-white font-bold"
              >
                <option value={3}>最大 3 本</option>
                <option value={5}>最大 5 本</option>
                <option value={10}>最大 10 本 (推奨)</option>
                <option value={15}>最大 15 本</option>
                <option value={20}>最大 20 本</option>
              </select>
              <p className="text-[11px] text-stone-500">
                1日の上限に達した場合、以降の定期実行は自動でスキップされます。
              </p>
            </div>
          </div>

          {/* 稼働時間帯コントロール */}
          <div className="bg-stone-50 p-5 rounded-2xl border border-stone-200 space-y-3">
            <h4 className="text-xs font-bold text-stone-800 flex items-center gap-1.5">
              <span>稼働時間帯コントロール（アクセス閑散期の深夜休止）</span>
            </h4>
            <div className="flex flex-wrap items-center gap-3 text-xs">
              <span>毎日</span>
              <select
                value={startHour}
                onChange={(e) => setStartHour(Number(e.target.value))}
                className="px-3 py-1.5 rounded-lg border border-stone-300 bg-white font-mono font-bold"
              >
                {[6, 7, 8, 9, 10].map((h) => (
                  <option key={h} value={h}>{String(h).padStart(2, '0')}:00 から</option>
                ))}
              </select>
              <span>〜</span>
              <select
                value={endHour}
                onChange={(e) => setEndHour(Number(e.target.value))}
                className="px-3 py-1.5 rounded-lg border border-stone-300 bg-white font-mono font-bold"
              >
                {[22, 23, 24].map((h) => (
                  <option key={h} value={h}>{String(h === 24 ? 24 : h).padStart(2, '0')}:00 まで</option>
                ))}
              </select>
              <span className="text-stone-500 text-[11px]">（※深夜 0:00〜7:00 は自動休止）</span>
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              className="px-6 py-2.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs transition-colors cursor-pointer shadow-xs"
            >
              スケジュール設定を保存
            </button>
          </div>
        </form>
      )}
    </div>
  );
};
