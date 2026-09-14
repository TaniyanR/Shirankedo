import React, { useState, useEffect } from 'react';
import {
  LayoutDashboard, Globe, Flame, FileText, Sliders, Cpu, Image as ImageIcon,
  Users, MessageSquare, Ban, ThumbsUp, Share2, ShieldAlert, FileCode,
  Search, RefreshCw, Plus, CheckCircle, AlertTriangle, Trash2, ArrowUpRight,
  Download, Terminal, Lock
} from 'lucide-react';
import { Site, Article, TrendCandidate, ImageItem, ImageGroup, BannedKeyword, SnsQueueItem, SystemLog } from '../types';

interface AdminConsoleProps {
  currentSite: Site;
  sites: Site[];
  onSelectSite: (siteId: number) => void;
  onRefreshSites: () => void;
}

type AdminTab =
  | 'dashboard'
  | 'sites'
  | 'trends'
  | 'articles'
  | 'index_settings'
  | 'ai_settings'
  | 'images'
  | 'image_groups'
  | 'comments'
  | 'banned_keywords'
  | 'votes'
  | 'sns'
  | 'held_articles'
  | 'seo'
  | 'export'
  | 'logs';

export const AdminConsole: React.FC<AdminConsoleProps> = ({
  currentSite,
  sites,
  onSelectSite,
  onRefreshSites,
}) => {
  const [activeTab, setActiveTab] = useState<AdminTab>('dashboard');
  const [stats, setStats] = useState<any>(null);
  const [trends, setTrends] = useState<TrendCandidate[]>([]);
  const [articles, setArticles] = useState<Article[]>([]);
  const [images, setImages] = useState<ImageItem[]>([]);
  const [groups, setGroups] = useState<ImageGroup[]>([]);
  const [bannedKeywords, setBannedKeywords] = useState<BannedKeyword[]>([]);
  const [snsQueue, setSnsQueue] = useState<SnsQueueItem[]>([]);
  const [logs, setLogs] = useState<SystemLog[]>([]);
  const [settings, setSettings] = useState<any>(null);

  // States for actions
  const [isGenerating, setIsGenerating] = useState(false);
  const [genKeyword, setGenKeyword] = useState('');
  const [genStatusMsg, setGenStatusMsg] = useState<string | null>(null);

  // New item forms
  const [newSubdomain, setNewSubdomain] = useState('');
  const [newSiteName, setNewSiteName] = useState('');
  const [newSiteGenre, setNewSiteGenre] = useState('general');

  const [newBannedWord, setNewBannedWord] = useState('');
  const [newMatchType, setNewMatchType] = useState<'exact' | 'partial' | 'regex'>('partial');
  const [newBannedReason, setNewBannedReason] = useState('');

  const [newImageUrl, setNewImageUrl] = useState('');
  const [newImageAlt, setNewImageAlt] = useState('');
  const [newImageGroupId, setNewImageGroupId] = useState('');
  const [newImageKeywords, setNewImageKeywords] = useState('');

  const [newGroupName, setNewGroupName] = useState('');
  const [newGroupKeywords, setNewGroupKeywords] = useState('');

  // Load initial data
  const loadData = () => {
    fetch('/api/dashboard-stats')
      .then((res) => res.json())
      .then((d) => setStats(d.stats))
      .catch(console.error);

    fetch('/api/trends')
      .then((res) => res.json())
      .then((d) => setTrends(d.trends || []))
      .catch(console.error);

    fetch('/api/articles?all_status=1')
      .then((res) => res.json())
      .then((d) => setArticles(d.articles || []))
      .catch(console.error);

    fetch('/api/images')
      .then((res) => res.json())
      .then((d) => {
        setImages(d.images || []);
        setGroups(d.groups || []);
      })
      .catch(console.error);

    fetch('/api/banned-keywords')
      .then((res) => res.json())
      .then((d) => setBannedKeywords(d.keywords || []))
      .catch(console.error);

    fetch('/api/sns-queue')
      .then((res) => res.json())
      .then((d) => setSnsQueue(d.queue || []))
      .catch(console.error);

    fetch('/api/logs')
      .then((res) => res.json())
      .then((d) => setLogs(d.logs || []))
      .catch(console.error);

    fetch('/api/settings')
      .then((res) => res.json())
      .then((d) => setSettings(d.settings))
      .catch(console.error);
  };

  useEffect(() => {
    loadData();
  }, [currentSite.id]);

  // Triggers
  const handleCollectTrends = async () => {
    const res = await fetch('/api/trends/collect', { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      loadData();
      alert(`トレンド収集完了: 新規${data.addedCount}件を取得しました`);
    }
  };

  const handleGenerateArticle = async (trendCandidateId?: number, customKeyword?: string) => {
    setIsGenerating(true);
    setGenStatusMsg('Gemini AIが事実関係を確認して記事を生成中...');
    try {
      const res = await fetch('/api/articles/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trendCandidateId, customKeyword }),
      });
      const data = await res.json();
      if (data.success) {
        setGenStatusMsg(`生成完了: 「${data.article.title}」(${data.article.status === 'on_hold' ? '⚠️危険ジャンル検知により自動保留' : '公開'})`);
        loadData();
      } else {
        setGenStatusMsg(`エラー: ${data.error}`);
      }
    } catch (err: any) {
      setGenStatusMsg(`生成失敗: ${err.message}`);
    } finally {
      setIsGenerating(false);
      setTimeout(() => setGenStatusMsg(null), 5000);
    }
  };

  const handleUpdateArticleStatus = async (id: number, status: string) => {
    await fetch(`/api/articles/${id}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status }),
    });
    loadData();
  };

  const handleProcessSnsQueue = async () => {
    const res = await fetch('/api/sns-queue/process', { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      loadData();
      alert(`SNS配信完了: ${data.processedCount}件のキューを処理しました`);
    }
  };

  const handleCreateSite = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newSiteName.trim()) return;
    const res = await fetch('/api/sites', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        subdomain: newSubdomain,
        name: newSiteName,
        genre: newSiteGenre,
      }),
    });
    const data = await res.json();
    if (data.success) {
      setNewSiteName('');
      setNewSubdomain('');
      onRefreshSites();
      alert(`新サイト「${data.site.name}」を作成しました`);
    }
  };

  const handleAddBannedKeyword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newBannedWord.trim()) return;
    const res = await fetch('/api/banned-keywords', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        keyword: newBannedWord,
        matchType: newMatchType,
        reason: newBannedReason,
      }),
    });
    const data = await res.json();
    if (data.success) {
      setNewBannedWord('');
      setNewBannedReason('');
      loadData();
    }
  };

  const handleDeleteBannedKeyword = async (id: number) => {
    await fetch(`/api/banned-keywords/${id}`, { method: 'DELETE' });
    loadData();
  };

  const handleAddImage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newImageUrl.trim()) return;
    const res = await fetch('/api/images', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        url: newImageUrl,
        altText: newImageAlt,
        groupId: newImageGroupId || undefined,
        keywords: newImageKeywords,
      }),
    });
    const data = await res.json();
    if (data.success) {
      setNewImageUrl('');
      setNewImageAlt('');
      setNewImageKeywords('');
      loadData();
      alert('画像をライブラリに登録しました');
    }
  };

  const handleAddGroup = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newGroupName.trim()) return;
    const res = await fetch('/api/image-groups', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: newGroupName,
        genre: 'entertainment',
        keywords: newGroupKeywords,
      }),
    });
    const data = await res.json();
    if (data.success) {
      setNewGroupName('');
      setNewGroupKeywords('');
      loadData();
      alert(`画像グループ「${data.group.name}」を登録しました`);
    }
  };

  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    await fetch('/api/settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(settings),
    });
    alert('設定を保存しました');
  };

  // Render Tabs
  return (
    <div className="bg-stone-100 min-h-screen pb-16">
      {/* Top Admin Header */}
      <div className="bg-stone-900 text-white px-6 py-4 border-b border-stone-800">
        <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-amber-500 text-stone-950 font-black flex items-center justify-center text-sm">
              管
            </div>
            <div>
              <h1 className="text-lg font-black tracking-tight">「しらんけど」管理システム</h1>
              <p className="text-xs text-stone-400">
                操作中サイト: <strong className="text-amber-400">{currentSite.name}</strong> ({currentSite.subdomain ? `${currentSite.subdomain}.example.com` : 'メイン総合'})
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={handleCollectTrends}
              className="px-3 py-1.5 rounded-lg bg-stone-800 hover:bg-stone-700 text-xs font-bold text-stone-200 flex items-center gap-1.5 transition-colors border border-stone-700"
            >
              <RefreshCw className="w-3.5 h-3.5 text-amber-400" />
              <span>トレンド手動収集</span>
            </button>

            <button
              onClick={handleProcessSnsQueue}
              className="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-xs font-bold text-stone-950 flex items-center gap-1.5 transition-colors shadow-2xs"
            >
              <Share2 className="w-3.5 h-3.5" />
              <span>SNSキュー即時配信</span>
            </button>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-6 grid grid-cols-1 md:grid-cols-5 gap-6">
        {/* Navigation Sidebar */}
        <aside className="md:col-span-1 space-y-1">
          <div className="bg-white rounded-2xl border border-stone-200 p-2 shadow-xs space-y-0.5">
            {[
              { id: 'dashboard', label: 'ダッシュボード', icon: LayoutDashboard },
              { id: 'sites', label: 'マルチサイト管理', icon: Globe },
              { id: 'trends', label: 'トレンド候補一覧', icon: Flame },
              { id: 'articles', label: '記事管理', icon: FileText },
              { id: 'held_articles', label: '危険・保留記事', icon: ShieldAlert },
              { id: 'index_settings', label: 'しらんけど指数設定', icon: Sliders },
              { id: 'ai_settings', label: 'AI自動生成設定', icon: Cpu },
              { id: 'images', label: '画像ライブラリ (1万枚)', icon: ImageIcon },
              { id: 'image_groups', label: '画像グループ管理', icon: Users },
              { id: 'banned_keywords', label: '拒否キーワード', icon: Ban },
              { id: 'sns', label: 'SNS自動配信キュー', icon: Share2 },
              { id: 'seo', label: 'SEO・サイトマップ', icon: FileCode },
              { id: 'export', label: 'PHP+MySQL パッケージ', icon: Download },
              { id: 'logs', label: 'システム動作ログ', icon: Terminal },
            ].map((tab) => {
              const Icon = tab.icon;
              const isActive = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as AdminTab)}
                  className={`w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold transition-colors text-left ${
                    isActive
                      ? 'bg-amber-500 text-stone-950'
                      : 'text-stone-700 hover:bg-stone-100'
                  }`}
                >
                  <Icon className="w-4 h-4 shrink-0" />
                  <span className="truncate">{tab.label}</span>
                </button>
              );
            })}
          </div>
        </aside>

        {/* Main Content View Area */}
        <main className="md:col-span-4 space-y-6">
          {/* Status Message */}
          {genStatusMsg && (
            <div className="p-3 bg-amber-100 border border-amber-300 text-amber-950 rounded-xl text-xs font-bold flex items-center gap-2 animate-fade-in">
              <RefreshCw className="w-4 h-4 animate-spin text-amber-700" />
              <span>{genStatusMsg}</span>
            </div>
          )}

          {/* TAB 1: DASHBOARD */}
          {activeTab === 'dashboard' && (
            <div className="space-y-6">
              {/* Metric Cards */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs">
                  <div className="text-xs text-stone-500 font-medium">本日取得トレンド</div>
                  <div className="text-2xl font-black text-stone-900 mt-1">
                    {stats?.todayTrendsCount || trends.length}件
                  </div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs">
                  <div className="text-xs text-stone-500 font-medium">公開済み記事</div>
                  <div className="text-2xl font-black text-emerald-600 mt-1">
                    {stats?.autoPublishedCount || articles.filter((a) => a.status === 'published').length}件
                  </div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs">
                  <div className="text-xs text-stone-500 font-medium">安全保留 (ブレーキ)</div>
                  <div className="text-2xl font-black text-red-600 mt-1">
                    {stats?.heldCount || articles.filter((a) => a.status === 'on_hold').length}件
                  </div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs">
                  <div className="text-xs text-stone-500 font-medium">SNS配信成功</div>
                  <div className="text-2xl font-black text-blue-600 mt-1">
                    {stats?.snsSuccessCount || snsQueue.filter((s) => s.status === 'success').length}件
                  </div>
                </div>
              </div>

              {/* Quick AI Generator */}
              <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                  <Cpu className="w-4 h-4 text-amber-600" />
                  Gemini AI 即時記事生成テスト
                </h3>
                <p className="text-xs text-stone-500">
                  任意のキーワードを入力すると、公式情報源照合・安全判定・末尾「〜しらんけど。」のルールに沿って自動生成します。
                </p>

                <div className="flex gap-2">
                  <input
                    type="text"
                    value={genKeyword}
                    onChange={(e) => setGenKeyword(e.target.value)}
                    placeholder="例: 新作アニメ特報, 人気ゲームDLC"
                    className="flex-1 rounded-xl border border-stone-300 px-3 py-2 text-xs outline-hidden focus:ring-2 focus:ring-amber-500"
                  />
                  <button
                    onClick={() => handleGenerateArticle(undefined, genKeyword)}
                    disabled={isGenerating || !genKeyword.trim()}
                    className="px-4 py-2 bg-stone-900 hover:bg-stone-800 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition-colors shadow-2xs"
                  >
                    {isGenerating ? '生成中...' : 'AI記事生成'}
                  </button>
                </div>
              </div>

              {/* Recent Articles */}
              <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                <h3 className="font-bold text-sm text-stone-900">直近の記事一覧</h3>
                <div className="divide-y divide-stone-100">
                  {articles.slice(0, 5).map((art) => (
                    <div key={art.id} className="py-2.5 flex items-center justify-between gap-2">
                      <div className="truncate pr-2">
                        <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded mr-2 ${
                          art.status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                        }`}>
                          {art.status === 'published' ? '公開中' : '保留'}
                        </span>
                        <span className="text-xs font-bold text-stone-800">{art.title}</span>
                      </div>
                      <span className="text-xs text-stone-500 font-mono shrink-0">指数: {art.shirankedoIndex}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* TAB 2: SITES */}
          {activeTab === 'sites' && (
            <div className="space-y-6">
              <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
                <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                  <Globe className="w-4 h-4 text-amber-500" />
                  マルチサイト一覧 (ワイルドカードサブドメイン)
                </h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {sites.map((s) => (
                    <div key={s.id} className="p-3 bg-stone-50 border border-stone-200 rounded-xl space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-stone-900">{s.name}</span>
                        <span className="text-[10px] font-mono bg-stone-200 px-1.5 py-0.5 rounded text-stone-700">
                          {s.subdomain ? `${s.subdomain}.example.com` : 'example.com (総合)'}
                        </span>
                      </div>
                      <p className="text-xs text-stone-500">{s.description}</p>
                      <div className="text-[11px] text-stone-600 flex items-center gap-2 pt-1">
                        <span>ジャンル: {s.genre}</span>
                        <span>自動公開: {s.allowAutoPublish ? 'ON' : 'OFF'}</span>
                      </div>
                    </div>
                  ))}
                </div>

                <form onSubmit={handleCreateSite} className="border-t border-stone-200 pt-4 space-y-3">
                  <h4 className="text-xs font-bold text-stone-900">＋ 新規サブサイト追加</h4>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input
                      type="text"
                      placeholder="サブドメイン (例: manga)"
                      value={newSubdomain}
                      onChange={(e) => setNewSubdomain(e.target.value)}
                      className="p-2 border border-stone-300 rounded-xl text-xs"
                    />
                    <input
                      type="text"
                      placeholder="サイト名 (例: しらんけど マンガ速報)"
                      value={newSiteName}
                      onChange={(e) => setNewSiteName(e.target.value)}
                      className="p-2 border border-stone-300 rounded-xl text-xs"
                    />
                    <select
                      value={newSiteGenre}
                      onChange={(e) => setNewSiteGenre(e.target.value)}
                      className="p-2 border border-stone-300 rounded-xl text-xs"
                    >
                      <option value="general">総合</option>
                      <option value="game">ゲーム</option>
                      <option value="entertainment">エンタメ</option>
                      <option value="manga">マンガ・アニメ</option>
                      <option value="tech">テクノロジー</option>
                    </select>
                  </div>
                  <button
                    type="submit"
                    className="px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold hover:bg-stone-800"
                  >
                    サブサイト作成
                  </button>
                </form>
              </div>
            </div>
          )}

          {/* TAB 3: TREND CANDIDATES */}
          {activeTab === 'trends' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                  <Flame className="w-4 h-4 text-red-500" />
                  収集済みトレンド候補 ({trends.length}件)
                </h3>
                <button
                  onClick={handleCollectTrends}
                  className="px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-800 rounded-lg text-xs font-bold flex items-center gap-1"
                >
                  <RefreshCw className="w-3 h-3" />
                  再取得
                </button>
              </div>

              <div className="divide-y divide-stone-100">
                {trends.map((t) => (
                  <div key={t.id} className="py-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-black text-stone-900">{t.displayKeyword}</span>
                        {t.isRapidRise && (
                          <span className="text-[10px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded">
                            🔥 急上昇 +{Math.round(t.growthRate)}%
                          </span>
                        )}
                      </div>
                      <div className="text-[11px] text-stone-500 flex items-center gap-2 mt-1">
                        <span>しらんけど指数: <strong className="text-stone-800">{t.shirankedoIndex}点</strong></span>
                        <span>|</span>
                        <span>ソース: {t.sources.join(', ')}</span>
                      </div>
                    </div>

                    <button
                      onClick={() => handleGenerateArticle(t.id, t.displayKeyword)}
                      disabled={isGenerating}
                      className="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs rounded-xl shadow-2xs"
                    >
                      AI記事化
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 4: ARTICLES */}
          {activeTab === 'articles' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <FileText className="w-4 h-4 text-amber-500" />
                記事一覧・ステータス管理 ({articles.length}件)
              </h3>

              <div className="space-y-3">
                {articles.map((a) => (
                  <div key={a.id} className="p-3 bg-stone-50 border border-stone-200 rounded-xl flex flex-wrap items-center justify-between gap-3">
                    <div className="flex-1 min-w-[240px]">
                      <div className="flex items-center gap-2">
                        <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded ${
                          a.status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                        }`}>
                          {a.status}
                        </span>
                        <h4 className="text-xs font-bold text-stone-900">{a.title}</h4>
                      </div>
                      <div className="text-[11px] text-stone-500 mt-1">
                        指数: {a.shirankedoIndex} / 締め: 「{a.conclusionSentence.slice(-14)}」
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      {a.status !== 'published' && (
                        <button
                          onClick={() => handleUpdateArticleStatus(a.id, 'published')}
                          className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold"
                        >
                          公開承認
                        </button>
                      )}
                      {a.status === 'published' && (
                        <button
                          onClick={() => handleUpdateArticleStatus(a.id, 'on_hold')}
                          className="px-2.5 py-1 bg-stone-300 hover:bg-stone-400 text-stone-800 rounded-lg text-xs font-bold"
                        >
                          保留に戻す
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 5: HELD DANGER ARTICLES */}
          {activeTab === 'held_articles' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <div className="flex items-center gap-2 text-rose-700">
                <ShieldAlert className="w-5 h-5" />
                <h3 className="font-bold text-sm">危険記事・自動保留リスト（安全ブレーキ作動中）</h3>
              </div>
              <p className="text-xs text-stone-600">
                犯罪、訃報、病気、スキャンダル、未成年、一般人の特定などの危険キーワードが含まれる記事、または公式裏付けが不足している記事を自動保留しています。
              </p>

              <div className="space-y-3">
                {articles.filter((a) => a.status === 'on_hold' || a.isDangerous).map((a) => (
                  <div key={a.id} className="p-4 bg-rose-50 border border-rose-200 rounded-xl space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-bold text-rose-900">{a.title}</span>
                      <span className="text-[10px] font-bold bg-rose-200 text-rose-800 px-2 py-0.5 rounded">
                        保留中
                      </span>
                    </div>
                    <div className="text-xs text-rose-800 bg-white/70 p-2 rounded-md font-mono">
                      理由: {a.dangerReason || '危険キーワード検知 / 一次ソース確認待ち'}
                    </div>
                    <p className="text-xs text-stone-700 line-clamp-2">{a.whyTrending}</p>
                    <div className="flex justify-end gap-2 pt-2">
                      <button
                        onClick={() => handleUpdateArticleStatus(a.id, 'published')}
                        className="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-500"
                      >
                        安全を確認したため公開
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 6: INDEX SETTINGS */}
          {activeTab === 'index_settings' && settings && (
            <form onSubmit={handleSaveSettings} className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <Sliders className="w-4 h-4 text-amber-500" />
                しらんけど指数 配点＆急上昇設定
              </h3>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div className="space-y-1">
                  <label className="font-bold text-stone-700">Googleトレンド配点 (現在: {settings.weights?.google || 30}点)</label>
                  <input
                    type="range"
                    min="0"
                    max="50"
                    value={settings.weights?.google || 30}
                    onChange={(e) => setSettings({
                      ...settings,
                      weights: { ...settings.weights, google: parseInt(e.target.value, 10) }
                    })}
                    className="w-full"
                  />
                </div>

                <div className="space-y-1">
                  <label className="font-bold text-stone-700">Yahoo!リアルタイム配点 (現在: {settings.weights?.yahoo || 25}点)</label>
                  <input
                    type="range"
                    min="0"
                    max="50"
                    value={settings.weights?.yahoo || 25}
                    onChange={(e) => setSettings({
                      ...settings,
                      weights: { ...settings.weights, yahoo: parseInt(e.target.value, 10) }
                    })}
                    className="w-full"
                  />
                </div>

                <div className="space-y-1">
                  <label className="font-bold text-stone-700">ニュースランキング配点 (現在: {settings.weights?.news || 20}点)</label>
                  <input
                    type="range"
                    min="0"
                    max="50"
                    value={settings.weights?.news || 20}
                    onChange={(e) => setSettings({
                      ...settings,
                      weights: { ...settings.weights, news: parseInt(e.target.value, 10) }
                    })}
                    className="w-full"
                  />
                </div>

                <div className="space-y-1">
                  <label className="font-bold text-stone-700">YouTube配点 (現在: {settings.weights?.youtube || 15}点)</label>
                  <input
                    type="range"
                    min="0"
                    max="50"
                    value={settings.weights?.youtube || 15}
                    onChange={(e) => setSettings({
                      ...settings,
                      weights: { ...settings.weights, youtube: parseInt(e.target.value, 10) }
                    })}
                    className="w-full"
                  />
                </div>
              </div>

              <div className="border-t border-stone-200 pt-3">
                <button type="submit" className="px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold">
                  設定を保存
                </button>
              </div>
            </form>
          )}

          {/* TAB 7: AI SETTINGS */}
          {activeTab === 'ai_settings' && settings && (
            <form onSubmit={handleSaveSettings} className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <Cpu className="w-4 h-4 text-amber-500" />
                AI記事生成設定 (Gemini API / マルチプロバイダー)
              </h3>

              <div className="space-y-3 text-xs">
                <div>
                  <label className="font-bold text-stone-700 block mb-1">使用モデル</label>
                  <input
                    type="text"
                    value={settings.aiModel || 'gemini-3.8-flash'}
                    onChange={(e) => setSettings({ ...settings, aiModel: e.target.value })}
                    className="w-full p-2 border border-stone-300 rounded-xl"
                  />
                </div>

                <div>
                  <label className="font-bold text-stone-700 block mb-1">AIシステムプロンプト (指示書)</label>
                  <textarea
                    rows={4}
                    value={settings.aiSystemPrompt || ''}
                    onChange={(e) => setSettings({ ...settings, aiSystemPrompt: e.target.value })}
                    className="w-full p-2 border border-stone-300 rounded-xl"
                  />
                  <span className="text-[11px] text-stone-500">
                    ※客観事実のみの要約、容疑者犯人扱いの禁止、末尾「〜しらんけど。」の厳守を含めます。
                  </span>
                </div>
              </div>

              <button type="submit" className="px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold">
                設定を保存
              </button>
            </form>
          )}

          {/* TAB 8: IMAGES (10,000 Capacity Virtual Grid) */}
          {activeTab === 'images' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                  <ImageIcon className="w-4 h-4 text-amber-500" />
                  画像ライブラリ (登録数: {images.length}枚 / 最大10,000枚規模対応)
                </h3>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {images.map((img) => (
                  <div key={img.id} className="border border-stone-200 rounded-xl overflow-hidden bg-stone-50">
                    <img
                      src={img.url}
                      alt={img.altText}
                      referrerPolicy="no-referrer"
                      className="w-full h-24 object-cover"
                    />
                    <div className="p-2 space-y-1">
                      <div className="text-[11px] font-bold text-stone-800 truncate">{img.altText}</div>
                      <div className="text-[10px] text-stone-500 flex justify-between">
                        <span>使用: {img.useCount}回</span>
                        <span>{img.groupName || '汎用'}</span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Add Image Form */}
              <form onSubmit={handleAddImage} className="border-t border-stone-200 pt-4 space-y-3">
                <h4 className="text-xs font-bold text-stone-900">＋ 画像を新規登録</h4>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <input
                    type="url"
                    placeholder="画像URL (著作権クリア済み)"
                    value={newImageUrl}
                    onChange={(e) => setNewImageUrl(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  />
                  <input
                    type="text"
                    placeholder="alt説明文 (例: ステージマイク)"
                    value={newImageAlt}
                    onChange={(e) => setNewImageAlt(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  />
                  <select
                    value={newImageGroupId}
                    onChange={(e) => setNewImageGroupId(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  >
                    <option value="">グループなし (汎用)</option>
                    {groups.map((g) => (
                      <option key={g.id} value={g.id}>{g.name}</option>
                    ))}
                  </select>
                  <input
                    type="text"
                    placeholder="キーワード (カンマ区切り: 千鳥, 大悟)"
                    value={newImageKeywords}
                    onChange={(e) => setNewImageKeywords(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  />
                </div>
                <button type="submit" className="px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold">
                  画像をライブラリに保存
                </button>
              </form>
            </div>
          )}

          {/* TAB 9: IMAGE GROUPS */}
          {activeTab === 'image_groups' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <Users className="w-4 h-4 text-amber-500" />
                画像グループ管理 (人物・作品・ゲーム単位の束ね)
              </h3>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {groups.map((g) => (
                  <div key={g.id} className="p-3 bg-stone-50 border border-stone-200 rounded-xl space-y-1">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-bold text-stone-900">{g.name}</span>
                      <span className="text-[10px] text-stone-500 font-mono">{g.genre}</span>
                    </div>
                    <div className="text-[11px] text-stone-600">
                      紐付けキーワード: {g.keywords.join(', ')}
                    </div>
                  </div>
                ))}
              </div>

              <form onSubmit={handleAddGroup} className="border-t border-stone-200 pt-4 space-y-3">
                <h4 className="text-xs font-bold text-stone-900">＋ 新規グループ追加</h4>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <input
                    type="text"
                    placeholder="グループ名 (例: ダウンタウン)"
                    value={newGroupName}
                    onChange={(e) => setNewGroupName(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  />
                  <input
                    type="text"
                    placeholder="キーワード (カンマ区切り: 松本人志, 浜田雅功)"
                    value={newGroupKeywords}
                    onChange={(e) => setNewGroupKeywords(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  />
                </div>
                <button type="submit" className="px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold">
                  グループ作成
                </button>
              </form>
            </div>
          )}

          {/* TAB 10: BANNED KEYWORDS */}
          {activeTab === 'banned_keywords' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <Ban className="w-4 h-4 text-rose-600" />
                拒否キーワード一覧 (誹謗中傷・個人情報・脅迫防止)
              </h3>
              <p className="text-xs text-stone-600">
                コメント投稿時に照合され、該当する投稿は完全遮断されます。
              </p>

              <div className="divide-y divide-stone-100">
                {bannedKeywords.map((k) => (
                  <div key={k.id} className="py-2 flex items-center justify-between gap-2 text-xs">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-rose-900 font-mono bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                        {k.keyword}
                      </span>
                      <span className="text-[10px] text-stone-500">[{k.matchType}]</span>
                      <span className="text-stone-600">{k.reason}</span>
                    </div>
                    <button
                      onClick={() => handleDeleteBannedKeyword(k.id)}
                      className="text-stone-400 hover:text-rose-600"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>

              <form onSubmit={handleAddBannedKeyword} className="border-t border-stone-200 pt-4 space-y-3">
                <h4 className="text-xs font-bold text-stone-900">＋ 拒否キーワード追加</h4>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                  <input
                    type="text"
                    placeholder="禁止ワード"
                    value={newBannedWord}
                    onChange={(e) => setNewBannedWord(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  />
                  <select
                    value={newMatchType}
                    onChange={(e: any) => setNewMatchType(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  >
                    <option value="partial">部分一致</option>
                    <option value="exact">完全一致</option>
                    <option value="regex">正規表現</option>
                  </select>
                  <input
                    type="text"
                    placeholder="理由 (例: 脅迫防止)"
                    value={newBannedReason}
                    onChange={(e) => setNewBannedReason(e.target.value)}
                    className="p-2 border border-stone-300 rounded-xl"
                  />
                </div>
                <button type="submit" className="px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold">
                  キーワードを追加
                </button>
              </form>
            </div>
          )}

          {/* TAB 11: SNS QUEUE */}
          {activeTab === 'sns' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                  <Share2 className="w-4 h-4 text-blue-500" />
                  SNS配信キュー (X / Pinterest / Instagram)
                </h3>
                <button
                  onClick={handleProcessSnsQueue}
                  className="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-stone-950 rounded-xl text-xs font-bold shadow-2xs"
                >
                  未配信キューを今すぐ送信
                </button>
              </div>

              <div className="space-y-3">
                {snsQueue.map((q) => (
                  <div key={q.id} className="p-3 bg-stone-50 border border-stone-200 rounded-xl text-xs space-y-1.5">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-stone-900 uppercase">
                        【{q.snsType}】{q.articleTitle || '記事配信'}
                      </span>
                      <span className={`text-[10px] font-bold px-2 py-0.5 rounded ${
                        q.status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                      }`}>
                        {q.status === 'success' ? '配信済み' : '配信待ち'}
                      </span>
                    </div>
                    <p className="text-stone-700 bg-white p-2 rounded-lg whitespace-pre-wrap border border-stone-100">
                      {q.postContent}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 12: SEO & SITEMAP */}
          {activeTab === 'seo' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4 text-xs">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <FileCode className="w-4 h-4 text-emerald-600" />
                SEO・サイトマップ・クローラー設定
              </h3>

              <div className="space-y-2">
                <div className="p-3 bg-stone-50 border border-stone-200 rounded-xl flex items-center justify-between">
                  <div>
                    <span className="font-bold text-stone-800 block">XML サイトマップ</span>
                    <span className="text-stone-500 font-mono">/sitemap.xml</span>
                  </div>
                  <a href="/sitemap.xml" target="_blank" rel="noreferrer" className="text-blue-600 hover:underline font-bold">
                    XMLを開く
                  </a>
                </div>

                <div className="p-3 bg-stone-50 border border-stone-200 rounded-xl flex items-center justify-between">
                  <div>
                    <span className="font-bold text-stone-800 block">Robots.txt</span>
                    <span className="text-stone-500 font-mono">/robots.txt</span>
                  </div>
                  <a href="/robots.txt" target="_blank" rel="noreferrer" className="text-blue-600 hover:underline font-bold">
                    テキストを開く
                  </a>
                </div>
              </div>
            </div>
          )}

          {/* TAB 13: EXPORT STANDALONE PHP+MYSQL */}
          {activeTab === 'export' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <Download className="w-4 h-4 text-amber-500" />
                完全独立 PHP+MySQL 配布パッケージ
              </h3>
              <p className="text-xs text-stone-600 leading-relaxed">
                本リポジトリ内の <code className="bg-stone-100 px-1 py-0.5 rounded font-mono">/php/</code> ディレクトリには、
                WordPress不要・外部CMS不要で動作する完全なPHP+MySQL（MariaDB）プロダクションコード（PDO Prepared Statement・ワイルドカードサブドメインルーター・自動マイグレーション・cronスクリプト・管理画面）が同梱されています。
              </p>

              <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-2 text-xs">
                <div className="font-bold text-amber-950">📦 生成済みPHPパッケージ構成:</div>
                <ul className="list-disc pl-5 space-y-1 text-amber-900 font-mono">
                  <li>/php/database.sql (MySQL/MariaDB完全テーブル定義)</li>
                  <li>/php/config.php & /php/.htaccess (URLリライト)</li>
                  <li>/php/migrate.php (初回自動セットアップ)</li>
                  <li>/php/classes/ (Database, SiteManager, TrendCollector, ShirankedoIndex, SafetyBrake, AiArticleGenerator, ImageManager, SnsDispatcher, SpamFilter)</li>
                  <li>/php/cron/worker.php (定期実行自動収集・AI生成・SNS配信)</li>
                  <li>/php/shirankedo-about.php & /php/comment-rules.php (固定ページ)</li>
                </ul>
              </div>
            </div>
          )}

          {/* TAB 14: LOGS */}
          {activeTab === 'logs' && (
            <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
              <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                <Terminal className="w-4 h-4 text-stone-700" />
                システム動作ログ ({logs.length}件)
              </h3>

              <div className="bg-stone-950 text-stone-200 p-4 rounded-xl font-mono text-xs space-y-2 max-h-96 overflow-y-auto">
                {logs.map((l) => (
                  <div key={l.id} className="border-b border-stone-800 pb-1.5">
                    <span className="text-stone-500">[{new Date(l.createdAt).toLocaleTimeString()}]</span>{' '}
                    <span className="text-amber-400 font-bold">[{l.category}]</span>{' '}
                    <span>{l.message}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </main>
      </div>
    </div>
  );
};
