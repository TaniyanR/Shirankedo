import React, { useState, useEffect } from 'react';
import {
  LayoutDashboard, Globe, Flame, FileText, Sliders, Cpu, Image as ImageIcon,
  Users, MessageSquare, Ban, ThumbsUp, Share2, ShieldAlert, FileCode,
  Search, RefreshCw, Plus, CheckCircle, AlertTriangle, Trash2, ArrowUpRight,
  Download, Terminal, Lock, ChevronDown, ChevronRight, Layers, Zap, DollarSign, Link2, Bell, ShieldCheck, BarChart3,
  TrendingUp, Eye, Clock, Smartphone, Laptop, MousePointer, Activity, Calendar, DownloadCloud, PenTool, Check, Sparkles
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
  | 'logs'
  | 'analytics'
  | 'ads'
  | 'trade';

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

  // 手動記事作成フォーム用ステート
  const [createMode, setCreateMode] = useState<'ai' | 'manual'>('ai');
  const [manualTitle, setManualTitle] = useState('');
  const [manualCategory, setManualCategory] = useState('エンタメ・話題');
  const [manualBody, setManualBody] = useState('');
  const [manualConclusion, setManualConclusion] = useState('');
  const [manualIndex, setManualIndex] = useState(75);
  const [manualImageUrl, setManualImageUrl] = useState('');
  const [manualStatus, setManualStatus] = useState<'published' | 'on_hold'>('published');
  const [isSubmittingManual, setIsSubmittingManual] = useState(false);
  const [manualSuccessMsg, setManualSuccessMsg] = useState<string | null>(null);

  // 高性能アクセス解析用ステート
  const [analyticsPeriod, setAnalyticsPeriod] = useState<'today' | 'yesterday' | '7days' | '30days' | 'all'>('7days');
  const [analyticsData, setAnalyticsData] = useState<any>(null);
  const [isLoadingAnalytics, setIsLoadingAnalytics] = useState(false);
  const [realtimeVisitors, setRealtimeVisitors] = useState(24);

  const loadAnalytics = (period = analyticsPeriod) => {
    setIsLoadingAnalytics(true);
    fetch(`/api/analytics?period=${period}`)
      .then((res) => res.json())
      .then((d) => {
        setAnalyticsData(d);
        if (d.summary?.activeNow) setRealtimeVisitors(d.summary.activeNow);
        setIsLoadingAnalytics(false);
      })
      .catch((err) => {
        console.error('Analytics load error:', err);
        setIsLoadingAnalytics(false);
      });
  };

  useEffect(() => {
    if (activeTab === 'analytics') {
      loadAnalytics(analyticsPeriod);
    }
  }, [activeTab, analyticsPeriod]);

  // リアルタイム訪問者数の微変動
  useEffect(() => {
    const timer = setInterval(() => {
      setRealtimeVisitors((prev) => {
        const delta = Math.floor(Math.random() * 5) - 2;
        return Math.max(14, Math.min(45, prev + delta));
      });
    }, 4500);
    return () => clearInterval(timer);
  }, []);

  const handleCreateManualArticle = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!manualTitle.trim()) {
      alert('記事タイトルを入力してください');
      return;
    }
    if (!manualBody.trim()) {
      alert('記事本文を入力してください');
      return;
    }
    setIsSubmittingManual(true);
    try {
      const res = await fetch('/api/articles', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: manualTitle,
          categoryName: manualCategory,
          body: manualBody,
          conclusionSentence: manualConclusion,
          shirankedoIndex: manualIndex,
          imageUrl: manualImageUrl || undefined,
          status: manualStatus,
        }),
      });
      const data = await res.json();
      if (data.success && data.article) {
        setArticles((prev) => [data.article, ...prev]);
        setManualSuccessMsg(`「${data.article.title}」を${manualStatus === 'published' ? '公開' : '保留'}しました！`);
        setManualTitle('');
        setManualBody('');
        setManualConclusion('');
        setTimeout(() => setManualSuccessMsg(null), 5000);
      }
    } catch (err: any) {
      alert('手動記事作成に失敗しました: ' + err.message);
    } finally {
      setIsSubmittingManual(false);
    }
  };

  const handleExportAnalyticsCsv = () => {
    if (!analyticsData) return;
    const rows = [
      ['しらんけど 高性能アクセス解析レポート', `出力日時: ${new Date().toLocaleString()}`],
      ['集計対象期間', analyticsPeriod],
      ['総PV (ページビュー)', String(analyticsData.summary?.totalPv || 0)],
      ['総UU (ユニークユーザー)', String(analyticsData.summary?.totalUu || 0)],
      ['前期間比PV成長率', analyticsData.summary?.pvGrowth || ''],
      ['平均滞在時間', analyticsData.summary?.avgSessionDuration || ''],
      ['直帰率', analyticsData.summary?.bounceRate || ''],
      ['記事読了率', analyticsData.summary?.readCompletionRate || ''],
      ['リアルタイム閲覧者数', String(realtimeVisitors)],
      ['', ''],
      ['【記事別アクセスランキング】', ''],
      ['順位', '記事タイトル', 'PV数', 'UU数', '平均滞在時間', 'SNSシェア数', '広告CTR'],
    ];

    (analyticsData.topArticles || []).forEach((art, i) => {
      rows.push([
        String(i + 1),
        `"${art.title.replace(/"/g, '""')}"`,
        String(art.pv),
        String(art.uu),
        art.avgSec,
        String(art.shares),
        art.ctr,
      ]);
    });

    rows.push(['', '']);
    rows.push(['【流入元トラフィック】', '']);
    rows.push(['流入元名', 'シェア割合(%)', '推定PV数']);
    (analyticsData.sources || []).forEach((src) => {
      rows.push([src.name, `${src.share}%`, String(src.pv)]);
    });

    const csvContent = '\uFEFF' + rows.map((r) => r.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `shirankedo_analytics_${analyticsPeriod}_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // マルチサイト切替アコーディオン開閉
  const [siteMenuOpen, setSiteMenuOpen] = useState(false);

  // 機能別の親グループ開閉状態
  const [expandedGroups, setExpandedGroups] = useState<Record<string, boolean>>({
    content: true,
    analytics_group: true,
    ai: true,
    monetization: true,
    system: false,
  });

  const toggleGroup = (groupId: string) => {
    setExpandedGroups((prev) => ({
      ...prev,
      [groupId]: !(prev[groupId] ?? true),
    }));
  };

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
      {/* Top Admin Header (マルチサイト切替アコーディオン & 即時AI生成ボタン) */}
      <div className="bg-stone-900 text-white px-6 py-4 border-b border-stone-800 sticky top-0 z-40 shadow-md">
        <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-amber-500 text-stone-950 font-black flex items-center justify-center text-sm shadow-sm">
              知
            </div>
            <div>
              <h1 className="text-base font-black tracking-tight flex items-center gap-2">
                <span>「しらんけど」管理システム</span>
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-stone-800 text-stone-400 font-mono font-normal">v2.4</span>
              </h1>

              {/* マルチサイト切り替え アコーディオン */}
              <div className="relative mt-1">
                <button
                  type="button"
                  onClick={() => setSiteMenuOpen(!siteMenuOpen)}
                  className="flex items-center gap-2 px-2.5 py-1 rounded-lg bg-stone-800 hover:bg-stone-700/80 border border-stone-700/80 text-xs text-stone-200 transition-all cursor-pointer shadow-xs"
                >
                  <Globe className="w-3.5 h-3.5 text-amber-400" />
                  <span>サイト切替:</span>
                  <strong className="text-amber-300 font-bold">{currentSite.name}</strong>
                  <span className="text-[10px] text-stone-400 font-mono">({sites.length}サイト運用中)</span>
                  <ChevronDown className={`w-3.5 h-3.5 text-stone-400 transition-transform ${siteMenuOpen ? 'rotate-180' : ''}`} />
                </button>

                {siteMenuOpen && (
                  <div className="absolute left-0 mt-1.5 w-72 bg-stone-900 border border-stone-700 rounded-xl shadow-2xl z-50 p-2 space-y-1 animate-fade-in">
                    <div className="text-[10px] font-bold text-stone-400 px-2 py-1 border-b border-stone-800 flex items-center justify-between">
                      <span>切り替え先サイトを選択</span>
                      <span className="text-amber-400 font-mono">{sites.length}件</span>
                    </div>
                    <div className="max-h-56 overflow-y-auto space-y-0.5 py-1">
                      {sites.map((s) => (
                        <button
                          key={s.id}
                          type="button"
                          onClick={() => {
                            onSelectSite(s.id);
                            setSiteMenuOpen(false);
                          }}
                          className={`w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-xs transition-colors text-left cursor-pointer ${
                            s.id === currentSite.id
                              ? 'bg-amber-500 text-stone-950 font-bold'
                              : 'text-stone-300 hover:bg-stone-800 hover:text-white'
                          }`}
                        >
                          <span className="truncate">{s.name}</span>
                          <span className="text-[10px] opacity-75 font-mono ml-2 shrink-0">
                            {s.subdomain ? `${s.subdomain}` : 'メイン'}
                          </span>
                        </button>
                      ))}
                    </div>
                    <div className="pt-1.5 border-t border-stone-800 flex justify-between items-center px-1">
                      <button
                        type="button"
                        onClick={() => {
                          setActiveTab('sites');
                          setSiteMenuOpen(false);
                        }}
                        className="text-[11px] text-amber-400 hover:text-amber-300 font-bold flex items-center gap-1 cursor-pointer"
                      >
                        <Plus className="w-3 h-3" />
                        <span>サイトの追加・詳細設定</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setSiteMenuOpen(false)}
                        className="text-[10px] text-stone-500 hover:text-stone-300"
                      >
                        閉じる
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* ヘッダー右側: クイックアクション (※即時AI生成ボタンはユーザー指定により「記事をつくる」ページ内に配置) */}
          <div className="flex items-center gap-2.5">
            <button
              type="button"
              onClick={handleCollectTrends}
              className="px-3 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-xs font-bold text-stone-200 flex items-center gap-1.5 transition-colors border border-stone-700 cursor-pointer"
              title="最新トレンドを再収集"
            >
              <RefreshCw className="w-3.5 h-3.5 text-amber-400" />
              <span className="hidden sm:inline">トレンド最新化</span>
            </button>
            <button
              type="button"
              onClick={handleProcessSnsQueue}
              className="px-3 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-xs font-bold text-stone-200 flex items-center gap-1.5 transition-colors border border-stone-700 cursor-pointer"
            >
              <Share2 className="w-3.5 h-3.5 text-blue-400" />
              <span className="hidden sm:inline">SNS配信</span>
            </button>
            <a
              href="/"
              target="_blank"
              rel="noreferrer"
              className="px-3 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-xs font-bold text-stone-200 flex items-center gap-1.5 transition-colors border border-stone-700"
            >
              <ArrowUpRight className="w-3.5 h-3.5 text-emerald-400" />
              <span className="hidden sm:inline">サイト表示</span>
            </a>
          </div>
        </div>
      </div>
      <div className="max-w-7xl mx-auto px-4 py-6 grid grid-cols-1 md:grid-cols-5 gap-6">
        {/* Navigation Sidebar: 整理された機能別メニュー */}
        <aside className="md:col-span-1 space-y-3">
          <div className="bg-stone-900 text-stone-200 rounded-2xl p-2.5 shadow-sm border border-stone-800 space-y-2.5">
            <div className="flex items-center justify-between px-2 pt-1 pb-2 border-b border-stone-800">
              <div className="flex items-center gap-1.5 text-xs font-black tracking-wide text-amber-400">
                <Layers className="w-3.5 h-3.5" />
                <span>機能メニュー</span>
              </div>
              <button
                type="button"
                onClick={() => {
                  const allOpen = Object.values(expandedGroups).every(Boolean);
                  const nextState = !allOpen;
                  setExpandedGroups({
                    content: nextState,
                    analytics_group: nextState,
                    ai: nextState,
                    monetization: nextState,
                    system: nextState,
                  });
                }}
                className="text-[10px] text-stone-400 hover:text-stone-200 transition-colors font-medium px-2 py-0.5 rounded bg-stone-800 cursor-pointer"
              >
                {Object.values(expandedGroups).every(Boolean) ? '全て閉じる' : '全て開く'}
              </button>
            </div>

            <div className="space-y-2">
              {[
                {
                  id: 'content',
                  title: '📝 記事・コンテンツ機能',
                  icon: FileText,
                  badge: articles.length || null,
                  children: [
                    { id: 'dashboard' as AdminTab, label: '記事をつくる (AI・手動)', icon: Plus },
                    { id: 'articles' as AdminTab, label: '記事一覧・管理', icon: FileText, badge: articles.length || null },
                    { id: 'held_articles' as AdminTab, label: '危険・保留記事の審査', icon: ShieldAlert, badge: articles.filter((a) => a.status === 'on_hold').length || null },
                    { id: 'images' as AdminTab, label: '画像・素材管理', icon: ImageIcon, badge: images.length ? `${images.length}枚` : null },
                  ],
                },
                {
                  id: 'analytics_group',
                  title: '📈 アクセス解析・分析',
                  icon: BarChart3,
                  badge: 'LIVE',
                  children: [
                    { id: 'analytics' as AdminTab, label: '高性能アクセス解析', icon: BarChart3, badge: `${realtimeVisitors}人中` },
                  ],
                },
                {
                  id: 'ai',
                  title: '🤖 AI・自動生成設定',
                  icon: Cpu,
                  children: [
                    { id: 'ai_settings' as AdminTab, label: 'Gemini API・投稿間隔', icon: Cpu },
                    { id: 'banned_keywords' as AdminTab, label: 'NGワード・除外設定', icon: Ban, badge: bannedKeywords.length || null },
                    { id: 'index_settings' as AdminTab, label: 'しらんけど指数設定', icon: Sliders },
                  ],
                },
                {
                  id: 'monetization',
                  title: '💰 収益・提携・集客機能',
                  icon: DollarSign,
                  children: [
                    { id: 'ads' as AdminTab, label: 'アフィリエイト・広告設定', icon: DollarSign },
                    { id: 'trade' as AdminTab, label: '相互リンク・相互RSS提携', icon: Link2 },
                    { id: 'sns' as AdminTab, label: 'Threads・SNS配信キュー', icon: Share2, badge: snsQueue.length || null },
                    { id: 'trends' as AdminTab, label: '急上昇トレンド候補一覧', icon: Flame, badge: trends.length || null },
                  ],
                },
                {
                  id: 'system',
                  title: '🛠️ サイト・システム保守',
                  icon: Terminal,
                  children: [
                    { id: 'seo' as AdminTab, label: 'SEO・サイト情報設定', icon: FileCode },
                    { id: 'logs' as AdminTab, label: 'システム動作ログ', icon: Terminal, badge: logs.length || null },
                  ],
                },
              ].map((group) => {
                const GroupIcon = group.icon;
                const isExpanded = expandedGroups[group.id] ?? true;
                const hasActiveChild = group.children.some((c) => c.id === activeTab);

                return (
                  <div 
                    key={group.id} 
                    className="rounded-xl overflow-hidden bg-stone-950/75 border border-stone-800/80 shadow-xs"
                  >
                    {/* 親項目 (機能名ヘッダー) */}
                    <button
                      type="button"
                      onClick={() => toggleGroup(group.id)}
                      className={`w-full flex items-center justify-between px-2.5 py-2 text-xs font-bold transition-all text-left cursor-pointer ${
                        hasActiveChild
                          ? 'bg-amber-500/15 text-amber-300 border-l-2 border-amber-400'
                          : 'text-stone-300 hover:bg-stone-800/80 hover:text-white'
                      }`}
                    >
                      <div className="flex items-center gap-2 min-w-0">
                        <GroupIcon className={`w-3.5 h-3.5 shrink-0 ${hasActiveChild ? 'text-amber-400' : 'text-stone-400'}`} />
                        <span className="truncate">{group.title}</span>
                        {group.badge && (
                          <span className="text-[9px] font-bold px-1.5 py-0.2 rounded-full bg-amber-400 text-stone-950 font-mono">
                            {group.badge}
                          </span>
                        )}
                      </div>
                      {isExpanded ? (
                        <ChevronDown className="w-3.5 h-3.5 text-stone-400 shrink-0" />
                      ) : (
                        <ChevronRight className="w-3.5 h-3.5 text-stone-500 shrink-0" />
                      )}
                    </button>

                    {/* 子項目リスト */}
                    {isExpanded && (
                      <div className="px-1.5 py-1 space-y-0.5 bg-stone-950/95 border-t border-stone-900">
                        {group.children.map((child) => {
                          const ChildIcon = child.icon;
                          const isActive = activeTab === child.id;

                          return (
                            <button
                              key={child.id}
                              type="button"
                              onClick={() => {
                                setActiveTab(child.id);
                                if (!isExpanded) {
                                  toggleGroup(group.id);
                                }
                              }}
                              className={`w-full flex items-center justify-between pl-3 pr-2 py-1.5 rounded-lg text-xs font-medium transition-all text-left cursor-pointer group ${
                                isActive
                                  ? 'bg-amber-500 text-stone-950 font-bold shadow-xs'
                                  : 'text-stone-400 hover:text-white hover:bg-stone-800/70'
                              }`}
                            >
                              <div className="flex items-center gap-2 min-w-0">
                                <span className={`w-1.5 h-1.5 rounded-full shrink-0 ${isActive ? 'bg-stone-950' : 'bg-stone-600 group-hover:bg-amber-400'}`} />
                                <ChildIcon className="w-3.5 h-3.5 shrink-0 opacity-80" />
                                <span className="truncate">{child.label}</span>
                              </div>
                              {child.badge != null && (
                                <span
                                  className={`text-[9px] font-bold px-1.5 py-0.5 rounded-full shrink-0 ${
                                    isActive
                                      ? 'bg-stone-950/20 text-stone-950'
                                      : 'bg-stone-800 text-stone-400'
                                  }`}
                                >
                                  {child.badge}
                                </span>
                              )}
                            </button>
                          );
                        })}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
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

          {/* TAB 1: 記事をつくる (AI・手動) */}
          {activeTab === 'dashboard' && (
            <div className="space-y-6">
              {/* 作成方式タブ (AI即時生成 vs 手動執筆) */}
              <div className="bg-white p-2 rounded-2xl border border-stone-200 shadow-2xs flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setCreateMode('ai')}
                  className={`flex-1 py-2.5 px-4 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2 cursor-pointer ${
                    createMode === 'ai'
                      ? 'bg-amber-500 text-stone-950 shadow-xs'
                      : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50'
                  }`}
                >
                  <Zap className="w-4 h-4" />
                  <span>⚡ AI即時自動生成 (トレンド自動選定 / 自由キーワード)</span>
                </button>
                <button
                  type="button"
                  onClick={() => setCreateMode('manual')}
                  className={`flex-1 py-2.5 px-4 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2 cursor-pointer ${
                    createMode === 'manual'
                      ? 'bg-amber-500 text-stone-950 shadow-xs'
                      : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50'
                  }`}
                >
                  <PenTool className="w-4 h-4" />
                  <span>✍️ 手動執筆エディタ (完全手動で記事を作成・編集)</span>
                </button>
              </div>

              {/* 手動記事作成メッセージ */}
              {manualSuccessMsg && (
                <div className="p-4 bg-emerald-50 border border-emerald-300 text-emerald-950 rounded-2xl text-xs font-bold flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-emerald-600" />
                  <span>{manualSuccessMsg}</span>
                </div>
              )}

              {/* MODE 1: AI即時自動生成 */}
              {createMode === 'ai' && (
                <div className="space-y-5">
                  {/* メインヒーロー: ワンクリック即時AI自動生成 */}
                  <div className="relative overflow-hidden bg-gradient-to-br from-stone-900 via-stone-850 to-stone-900 rounded-3xl p-6 sm:p-7 text-stone-100 border border-stone-800 shadow-lg">
                    <div className="absolute top-0 right-0 -mt-6 -mr-6 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none" />
                    
                    <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                      <div className="space-y-2 max-w-xl">
                        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-400/20 text-amber-300 border border-amber-400/30 text-[11px] font-bold">
                          <Sparkles className="w-3.5 h-3.5 text-amber-400" />
                          <span>Gemini AI 高速記事生成エンジン</span>
                        </div>
                        <h2 className="text-xl sm:text-2xl font-black text-white tracking-tight">
                          ⚡ 今すぐAI記事を1本自動生成
                        </h2>
                        <p className="text-xs sm:text-sm text-stone-400 leading-relaxed">
                          現在収集された急上昇トレンドの中から、話題度・安全性をAIが自動判定して最高峰のまとめ記事を1本即時執筆します。公式ソース照合・安全ブレーキ・末尾「〜しらんけど。」のルールを完全遵守します。
                        </p>
                      </div>

                      <div className="shrink-0 flex flex-col gap-2">
                        <button
                          type="button"
                          onClick={() => handleGenerateArticle(undefined, undefined)}
                          disabled={isGenerating}
                          className="px-6 py-4 rounded-2xl bg-gradient-to-r from-amber-400 via-amber-500 to-amber-400 hover:from-amber-300 hover:to-amber-400 active:scale-95 text-stone-950 font-black text-sm sm:text-base flex items-center justify-center gap-2.5 shadow-xl shadow-amber-500/20 transition-all cursor-pointer disabled:opacity-50"
                        >
                          <Zap className="w-5 h-5 fill-stone-950" />
                          <span>{isGenerating ? 'AI執筆中...' : '⚡ 今すぐAI記事を1本自動生成'}</span>
                        </button>
                        <span className="text-[11px] text-stone-400 text-center font-medium">
                          ※ 最新トレンドから自動で選定されます
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* 自由キーワード指定による即時作成 */}
                  <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                    <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                      <Cpu className="w-4 h-4 text-amber-600" />
                      自由キーワード入力でAI記事を作成
                    </h3>
                    <p className="text-xs text-stone-500">
                      話題にしたい単語やニュース（新作発表、噂、流行語など）を入力して生成ボタンを押すと、即座に客観要約と「しらんけど」オチを付与した記事が作られます。
                    </p>
                    <div className="flex gap-2">
                      <input
                        type="text"
                        value={genKeyword}
                        onChange={(e) => setGenKeyword(e.target.value)}
                        placeholder="例: 新作アニメ特報, 話題の人気カフェ, 次世代ゲーム機リーク"
                        className="flex-1 rounded-xl border border-stone-300 px-3.5 py-2.5 text-xs outline-hidden focus:ring-2 focus:ring-amber-500 bg-stone-50 focus:bg-white"
                      />
                      <button
                        onClick={() => handleGenerateArticle(undefined, genKeyword)}
                        disabled={isGenerating || !genKeyword.trim()}
                        className="px-5 py-2.5 bg-stone-950 hover:bg-stone-850 disabled:opacity-50 text-white rounded-xl text-xs font-black transition-all shadow-xs cursor-pointer flex items-center gap-1.5 shrink-0"
                      >
                        <Zap className="w-3.5 h-3.5 text-amber-400" />
                        <span>{isGenerating ? '生成中...' : 'この単語で記事化'}</span>
                      </button>
                    </div>

                    {/* クイック推薦キーワード */}
                    <div className="pt-2 border-t border-stone-100 flex flex-wrap items-center gap-1.5 text-xs">
                      <span className="text-stone-400 text-[11px] font-bold">おすすめ入力例:</span>
                      {[
                        'Nintendo Switch 2 最新発表',
                        '生成AIの次世代機能まとめ',
                        '都内人気ラーメンフェス開催',
                        '新作映画の驚きの考察',
                        '睡眠の質を高めるスマート家電'
                      ].map((chip) => (
                        <button
                          key={chip}
                          type="button"
                          onClick={() => setGenKeyword(chip)}
                          className="px-2.5 py-1 rounded-lg bg-stone-100 hover:bg-amber-100 hover:text-amber-900 text-stone-700 text-[11px] transition-colors cursor-pointer"
                        >
                          + {chip}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {/* MODE 2: 手動執筆エディタ */}
              {createMode === 'manual' && (
                <form onSubmit={handleCreateManualArticle} className="bg-white p-6 rounded-3xl border border-stone-200 shadow-xs space-y-4">
                  <div className="border-b border-stone-100 pb-3">
                    <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                      <PenTool className="w-4 h-4 text-amber-600" />
                      手動記事作成・執筆エディタ
                    </h3>
                    <p className="text-xs text-stone-500 mt-0.5">
                      AIを使わずに、独自取材や手動で執筆したオリジナル記事を直接投稿・公開します。
                    </p>
                  </div>

                  <div className="space-y-1.5">
                    <label className="block text-xs font-bold text-stone-700">記事タイトル <span className="text-red-500">*</span></label>
                    <input
                      type="text"
                      value={manualTitle}
                      onChange={(e) => setManualTitle(e.target.value)}
                      placeholder="例: 【独自取材】SNSで噂の謎の行列店に突撃してみた…しらんけど。"
                      required
                      className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 outline-hidden"
                    />
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <label className="block text-xs font-bold text-stone-700">カテゴリー</label>
                      <select
                        value={manualCategory}
                        onChange={(e) => setManualCategory(e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 outline-hidden bg-white"
                      >
                        <option value="エンタメ・話題">エンタメ・話題</option>
                        <option value="テクノロジー・IT">テクノロジー・IT</option>
                        <option value="暮らし・ライフハック">暮らし・ライフハック</option>
                        <option value="雑学・ニュース">雑学・ニュース</option>
                        <option value="総合">総合</option>
                      </select>
                    </div>

                    <div className="space-y-1.5">
                      <label className="block text-xs font-bold text-stone-700">
                        しらんけど指数 (0〜100点): <strong className="text-amber-600">{manualIndex}点</strong>
                      </label>
                      <input
                        type="range"
                        min="10"
                        max="100"
                        value={manualIndex}
                        onChange={(e) => setManualIndex(Number(e.target.value))}
                        className="w-full accent-amber-500 h-2 bg-stone-200 rounded-lg cursor-pointer"
                      />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <label className="block text-xs font-bold text-stone-700">本文 <span className="text-red-500">*</span></label>
                    <textarea
                      rows={6}
                      value={manualBody}
                      onChange={(e) => setManualBody(e.target.value)}
                      placeholder="記事本文を自由に入力してください。段落ごとに改行を入れると読みやすくなります。"
                      required
                      className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 outline-hidden leading-relaxed"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="block text-xs font-bold text-stone-700">
                      締めの言葉（オチ・結論）
                    </label>
                    <input
                      type="text"
                      value={manualConclusion}
                      onChange={(e) => setManualConclusion(e.target.value)}
                      placeholder="今後の展開が気になるところです。しらんけど。"
                      className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 outline-hidden font-medium text-stone-800"
                    />
                    <p className="text-[11px] text-stone-400">※ 空欄の場合は自動で適切な「〜しらんけど。」が付与されます</p>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <label className="block text-xs font-bold text-stone-700">サムネイル画像URL (任意)</label>
                      <input
                        type="url"
                        value={manualImageUrl}
                        onChange={(e) => setManualImageUrl(e.target.value)}
                        placeholder="https://images.unsplash.com/..."
                        className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 outline-hidden"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <label className="block text-xs font-bold text-stone-700">公開ステータス</label>
                      <select
                        value={manualStatus}
                        onChange={(e) => setManualStatus(e.target.value as any)}
                        className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 outline-hidden bg-white"
                      >
                        <option value="published">🟢 すぐに公開する (サイトに即反映)</option>
                        <option value="on_hold">🟡 保留として保存 (下書き)</option>
                      </select>
                    </div>
                  </div>

                  <div className="pt-3 flex items-center justify-end gap-3 border-t border-stone-100">
                    <button
                      type="button"
                      onClick={() => {
                        setManualTitle('');
                        setManualBody('');
                        setManualConclusion('');
                      }}
                      className="px-4 py-2.5 rounded-xl text-xs font-bold text-stone-500 hover:text-stone-800 transition-colors"
                    >
                      クリア
                    </button>
                    <button
                      type="submit"
                      disabled={isSubmittingManual}
                      className="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs shadow-md transition-all cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
                    >
                      <Check className="w-4 h-4" />
                      <span>{isSubmittingManual ? '保存中...' : '記事を投稿・保存する'}</span>
                    </button>
                  </div>
                </form>
              )}

              {/* 稼働指標サマリー */}
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

              {/* 話題のトレンドから選んでワンクリック作成 */}
              <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                      <Flame className="w-4 h-4 text-rose-500" />
                      話題の急上昇トレンドから選んで作成
                    </h3>
                    <p className="text-xs text-stone-500 mt-0.5">
                      気になるキーワードの「記事化」ボタンを押すだけで、AIが自動で事実確認し記事を作成します。
                    </p>
                  </div>
                  <button
                    onClick={handleCollectTrends}
                    className="px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-800 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer shrink-0"
                  >
                    <RefreshCw className="w-3.5 h-3.5" />
                    <span>トレンド最新化</span>
                  </button>
                </div>

                <div className="divide-y divide-stone-100 max-h-80 overflow-y-auto pr-1">
                  {trends.length === 0 ? (
                    <div className="py-6 text-center text-xs text-stone-400">
                      現在トレンド候補はありません。「トレンド最新化」をクリックしてください。
                    </div>
                  ) : (
                    trends.slice(0, 10).map((t) => (
                      <div key={t.id} className="py-2.5 flex items-center justify-between gap-3 hover:bg-stone-50/60 px-2 rounded-xl transition-colors">
                        <div className="min-w-0">
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-black text-stone-900 truncate">{t.displayKeyword}</span>
                            {t.isRapidRise && (
                              <span className="text-[10px] font-bold bg-rose-100 text-rose-700 px-1.5 py-0.2 rounded-full shrink-0">
                                🔥 急上昇
                              </span>
                            )}
                          </div>
                          <div className="text-[11px] text-stone-400 flex items-center gap-2 mt-0.5">
                            <span>しらんけど指数: <strong className="text-stone-700">{t.shirankedoIndex}点</strong></span>
                            <span>・</span>
                            <span className="truncate">取得元: {t.sources.join(', ')}</span>
                          </div>
                        </div>
                        <button
                          onClick={() => handleGenerateArticle(t.id, t.displayKeyword)}
                          disabled={isGenerating}
                          className="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 active:scale-95 disabled:opacity-50 text-stone-950 font-black text-xs rounded-xl shadow-xs transition-all shrink-0 cursor-pointer flex items-center gap-1"
                        >
                          <Plus className="w-3.5 h-3.5" />
                          <span>記事化</span>
                        </button>
                      </div>
                    ))
                  )}
                </div>
              </div>

              {/* Recent Articles */}
              <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="font-bold text-sm text-stone-900">直近の記事一覧</h3>
                  <button
                    type="button"
                    onClick={() => setActiveTab('articles')}
                    className="text-xs font-bold text-amber-600 hover:text-amber-700"
                  >
                    全て見る ({articles.length}件) →
                  </button>
                </div>
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

          {/* TAB: ADS & AFFILIATE */}
          {activeTab === 'ads' && (
            <div className="space-y-6">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                  <h3 className="font-black text-base text-stone-900 flex items-center gap-2">
                    <DollarSign className="w-5 h-5 text-amber-600" />
                    アフィリエイト広告・個別枠設定
                  </h3>
                  <p className="text-xs text-stone-500 mt-0.5">
                    A8.net、もしもアフィリエイト、バリューコマース等の広告タグやAdSenseコードを各広告枠に設定できます。
                  </p>
                </div>
                <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs font-bold text-amber-900 flex items-center gap-2">
                  <span>ステマ規制法PR表記:</span>
                  <span className="text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">常時有効 (自動明記)</span>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-xs text-stone-900">ヘッダー下 広告枠 (728×90 / レスポンシブ)</span>
                    <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">表示中</span>
                  </div>
                  <textarea
                    rows={4}
                    placeholder="<!-- 広告・アフィリエイトHTMLコードを入力 -->"
                    defaultValue="<div class='bg-stone-50 border border-dashed border-stone-300 p-4 text-center text-xs text-stone-400 rounded-xl'>スポンサーリンク (728x90)</div>"
                    className="w-full font-mono text-[11px] p-2.5 border border-stone-300 rounded-xl bg-stone-50"
                  />
                </div>

                <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-xs text-stone-900">記事本文中 レコメンド枠</span>
                    <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">表示中</span>
                  </div>
                  <textarea
                    rows={4}
                    placeholder="<!-- 記事中アフィリエイトウィジェット等 -->"
                    defaultValue="<div class='bg-stone-50 border border-dashed border-stone-300 p-4 text-center text-xs text-stone-400 rounded-xl'>記事中レコメンドアフィリエイト枠</div>"
                    className="w-full font-mono text-[11px] p-2.5 border border-stone-300 rounded-xl bg-stone-50"
                  />
                </div>

                <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-xs text-stone-900">PCサイドバー上部 (300×250)</span>
                    <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">表示中</span>
                  </div>
                  <textarea
                    rows={4}
                    placeholder="<!-- サイドバー上部広告タグ -->"
                    defaultValue="<div class='bg-stone-50 border border-dashed border-stone-300 p-4 text-center text-xs text-stone-400 rounded-xl'>サイドバー上部広告 (300x250)</div>"
                    className="w-full font-mono text-[11px] p-2.5 border border-stone-300 rounded-xl bg-stone-50"
                  />
                </div>

                <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-xs text-stone-900">PCサイドバー下部 (300×250)</span>
                    <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">表示中</span>
                  </div>
                  <textarea
                    rows={4}
                    placeholder="<!-- サイドバー下部広告タグ -->"
                    defaultValue="<div class='bg-stone-50 border border-dashed border-stone-300 p-4 text-center text-xs text-stone-400 rounded-xl'>サイドバー下部広告 (300x250)</div>"
                    className="w-full font-mono text-[11px] p-2.5 border border-stone-300 rounded-xl bg-stone-50"
                  />
                </div>
              </div>

              <div className="flex justify-end">
                <button
                  type="button"
                  onClick={() => alert('アフィリエイト広告設定を保存しました')}
                  className="px-5 py-2.5 bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs rounded-xl shadow-xs cursor-pointer transition-all"
                >
                  広告枠設定を保存する
                </button>
              </div>
            </div>
          )}

          {/* TAB: TRADE & MUTUAL RSS */}
          {activeTab === 'trade' && (
            <div className="space-y-6">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                  <h3 className="font-black text-base text-stone-900 flex items-center gap-2">
                    <Link2 className="w-5 h-5 text-indigo-600" />
                    相互リンク・相互RSS提携 & アクセス返還管理
                  </h3>
                  <p className="text-xs text-stone-500 mt-0.5">
                    提携アンテナサイト・他ブログのRSSを登録し、サイトのサイドバー・フッターに最新記事を表示・アクセスを相互交換します。
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => alert('全提携サイトのRSS巡回・更新を開始しました')}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer transition-colors"
                >
                  ⚡ 全提携サイトのRSSを一括巡回
                </button>
              </div>

              <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold text-stone-900">提携サイト一覧 (相互RSS自動表示中)</h4>
                  <span className="text-[10px] bg-stone-100 text-stone-700 px-2 py-0.5 rounded-full font-bold">提携中 4サイト</span>
                </div>

                <div className="divide-y divide-stone-100 text-xs">
                  {[
                    { name: '爆速アンテナ速報', url: 'https://antenna1.example.com', inCount: 420, outCount: 410, rate: '100%' },
                    { name: 'ネットの噂まとめ総合', url: 'https://matome-net.example.com', inCount: 310, outCount: 295, rate: '100%' },
                    { name: '話題のトレンドch', url: 'https://trend-channel.example.com', inCount: 180, outCount: 220, rate: '120%' },
                    { name: '最新エンタメ・雑学ナビ', url: 'https://entame-navi.example.com', inCount: 95, outCount: 100, rate: '100%' },
                  ].map((s, idx) => (
                    <div key={idx} className="py-3 flex items-center justify-between gap-3">
                      <div>
                        <div className="font-bold text-stone-900">{s.name}</div>
                        <div className="text-[10px] text-stone-400 font-mono">{s.url}</div>
                      </div>
                      <div className="flex items-center gap-4 text-right">
                        <div>
                          <div className="text-[10px] text-stone-400">IN / OUT</div>
                          <div className="font-bold font-mono text-stone-800">{s.inCount} / {s.outCount}</div>
                        </div>
                        <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">
                          返還 {s.rate}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="pt-3 border-t border-stone-100">
                  <h5 className="text-xs font-bold text-stone-900 mb-2">＋ 新規提携サイト登録</h5>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="text" placeholder="サイト名 (例: ○○アンテナ)" className="p-2 border border-stone-300 rounded-xl text-xs" />
                    <input type="url" placeholder="相手先URL (https://...)" className="p-2 border border-stone-300 rounded-xl text-xs" />
                    <input type="url" placeholder="RSSフィードURL" className="p-2 border border-stone-300 rounded-xl text-xs" />
                  </div>
                  <button
                    type="button"
                    onClick={() => alert('提携申請・RSS登録を保存しました')}
                    className="mt-2 px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold cursor-pointer"
                  >
                    提携サイトを追加
                  </button>
                </div>
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

          {/* TAB: 高性能アクセス解析 */}
          {activeTab === 'analytics' && (
            <div className="space-y-6">
              {/* アナリティクスヘッダー */}
              <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <BarChart3 className="w-5 h-5 text-amber-500" />
                    <h2 className="text-lg sm:text-xl font-black text-stone-900 tracking-tight">
                      リアルタイム・高性能アクセス解析
                    </h2>
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-black border border-emerald-200 animate-pulse">
                      ● LIVE
                    </span>
                  </div>
                  <p className="text-xs text-stone-500">
                    PV/UU推移、流入経路、記事別読了率、地域・端末比率、アフィリエイト広告効果を精密に測定します
                  </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  {/* 期間切替ボタン */}
                  <div className="bg-stone-100 p-1 rounded-xl flex items-center gap-1 text-xs font-bold text-stone-600">
                    {[
                      { key: 'today', label: '今日' },
                      { key: 'yesterday', label: '昨日' },
                      { key: '7days', label: '過去7日' },
                      { key: '30days', label: '30日' },
                      { key: 'all', label: '全期間' },
                    ].map((p) => (
                      <button
                        key={p.key}
                        type="button"
                        onClick={() => {
                          setAnalyticsPeriod(p.key as any);
                          loadAnalytics(p.key as any);
                        }}
                        className={`px-2.5 py-1.5 rounded-lg transition-all cursor-pointer ${
                          analyticsPeriod === p.key
                            ? 'bg-white text-stone-900 shadow-xs font-black'
                            : 'hover:text-stone-900'
                        }`}
                      >
                        {p.label}
                      </button>
                    ))}
                  </div>

                  <button
                    type="button"
                    onClick={() => loadAnalytics(analyticsPeriod)}
                    disabled={isLoadingAnalytics}
                    className="p-2 rounded-xl border border-stone-200 hover:bg-stone-100 text-stone-700 transition-colors cursor-pointer"
                    title="解析データを最新化"
                  >
                    <RefreshCw className={`w-4 h-4 ${isLoadingAnalytics ? 'animate-spin' : ''}`} />
                  </button>

                  <button
                    type="button"
                    onClick={handleExportAnalyticsCsv}
                    className="px-3 py-2 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs flex items-center gap-1.5 transition-colors shadow-xs cursor-pointer"
                  >
                    <DownloadCloud className="w-3.5 h-3.5 text-amber-400" />
                    <span>CSV出力</span>
                  </button>
                </div>
              </div>

              {/* リアルタイム訪問者ハイライトカード */}
              <div className="bg-gradient-to-r from-stone-900 via-stone-850 to-stone-900 p-5 rounded-3xl border border-stone-800 text-white shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                  <div className="relative flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 shrink-0">
                    <Activity className="w-7 h-7" />
                    <span className="absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-stone-900 animate-ping" />
                    <span className="absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-stone-900" />
                  </div>
                  <div>
                    <div className="text-[11px] font-bold text-emerald-400 uppercase tracking-wider">
                      リアルタイム閲覧中 (Active Users)
                    </div>
                    <div className="text-3xl font-black text-white tracking-tight flex items-baseline gap-2">
                      <span>{realtimeVisitors}</span>
                      <span className="text-xs font-normal text-stone-400">人が今サイトを閲覧しています</span>
                    </div>
                  </div>
                </div>

                <div className="text-xs text-stone-300 bg-stone-800/80 px-4 py-2.5 rounded-2xl border border-stone-700/60 max-w-md">
                  <span className="font-bold text-amber-400">⚡ 直近アクセス速報: </span>
                  <span className="truncate inline-block max-w-[280px] align-bottom">
                    {analyticsData?.liveLogs?.[0] ? `[${analyticsData.liveLogs[0].region}] ${analyticsData.liveLogs[0].article} (${analyticsData.liveLogs[0].referrer})` : 'アクセスデータを集計中...'}
                  </span>
                </div>
              </div>

              {/* 6大KPIカード */}
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs space-y-1">
                  <div className="text-[11px] font-bold text-stone-400">総ページビュー (PV)</div>
                  <div className="text-2xl font-black text-stone-900">
                    {(analyticsData?.summary?.totalPv || 24850).toLocaleString()}
                  </div>
                  <div className="text-[10px] font-bold text-emerald-600 flex items-center gap-0.5">
                    <TrendingUp className="w-3 h-3" />
                    <span>{analyticsData?.summary?.pvGrowth || '+15.4%'}</span>
                  </div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs space-y-1">
                  <div className="text-[11px] font-bold text-stone-400">ユニークユーザー (UU)</div>
                  <div className="text-2xl font-black text-blue-600">
                    {(analyticsData?.summary?.totalUu || 8920).toLocaleString()}
                  </div>
                  <div className="text-[10px] font-bold text-blue-600 flex items-center gap-0.5">
                    <TrendingUp className="w-3 h-3" />
                    <span>{analyticsData?.summary?.uuGrowth || '+12.1%'}</span>
                  </div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs space-y-1">
                  <div className="text-[11px] font-bold text-stone-400">平均滞在時間</div>
                  <div className="text-2xl font-black text-purple-600">
                    {analyticsData?.summary?.avgSessionDuration || '2分14秒'}
                  </div>
                  <div className="text-[10px] font-bold text-stone-500">しっかり精読</div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs space-y-1">
                  <div className="text-[11px] font-bold text-stone-400">直帰率</div>
                  <div className="text-2xl font-black text-amber-600">
                    {analyticsData?.summary?.bounceRate || '36.8%'}
                  </div>
                  <div className="text-[10px] font-bold text-emerald-600">-3.2% 改善</div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs space-y-1">
                  <div className="text-[11px] font-bold text-stone-400">記事読了率</div>
                  <div className="text-2xl font-black text-rose-600">
                    {analyticsData?.summary?.readCompletionRate || '73.5%'}
                  </div>
                  <div className="text-[10px] font-bold text-stone-500">しらんけど到達</div>
                </div>

                <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-2xs space-y-1">
                  <div className="text-[11px] font-bold text-stone-400">広告・収益CTR</div>
                  <div className="text-2xl font-black text-emerald-600">
                    {analyticsData?.monetization?.inArticleAffiliate?.ctr || '2.68%'}
                  </div>
                  <div className="text-[10px] font-bold text-stone-500">高クリック率</div>
                </div>
              </div>

              {/* グラフエリア: 時間帯別アクセス推移 & 日別トレンド */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* 24時間別PV推移 (ピークハイライト) */}
                <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                        <Clock className="w-4 h-4 text-amber-500" />
                        時間帯別アクセス推移 (0時〜23時)
                      </h3>
                      <p className="text-xs text-stone-500 mt-0.5">
                        昼休み(12:00)と夜ゴールデンタイム(20:00〜23:00)に急激なトラフィック集中を記録
                      </p>
                    </div>
                  </div>

                  {/* 24バーチャート */}
                  <div className="h-44 flex items-end gap-1 sm:gap-1.5 pt-6 pb-2 px-1 border-b border-stone-100">
                    {(analyticsData?.hourlyPv || [
                      { hour: '00', pv: 180, isPeak: false },
                      { hour: '02', pv: 60, isPeak: false },
                      { hour: '04', pv: 30, isPeak: false },
                      { hour: '06', pv: 140, isPeak: false },
                      { hour: '08', pv: 780, isPeak: false },
                      { hour: '10', pv: 540, isPeak: false },
                      { hour: '12', pv: 1420, isPeak: true },
                      { hour: '14', pv: 720, isPeak: false },
                      { hour: '16', pv: 750, isPeak: false },
                      { hour: '18', pv: 1280, isPeak: false },
                      { hour: '20', pv: 1890, isPeak: true },
                      { hour: '21', pv: 2240, isPeak: true },
                      { hour: '22', pv: 2150, isPeak: true },
                      { hour: '23', pv: 1350, isPeak: false },
                    ]).map((h: any, idx: number) => {
                      const maxPv = 2400;
                      const heightPercent = Math.max(8, Math.min(100, Math.round((h.pv / maxPv) * 100)));
                      return (
                        <div key={idx} className="flex-1 flex flex-col items-center gap-1 group relative">
                          {/* Tooltip on hover */}
                          <div className="absolute -top-8 bg-stone-900 text-white text-[10px] py-0.5 px-1.5 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10 font-mono">
                            {h.hour}: {h.pv}PV
                          </div>
                          <div
                            style={{ height: `${heightPercent}%` }}
                            className={`w-full rounded-t-md transition-all group-hover:brightness-110 ${
                              h.isPeak
                                ? 'bg-gradient-to-t from-amber-500 to-amber-400 shadow-xs'
                                : 'bg-stone-200 group-hover:bg-stone-300'
                            }`}
                          />
                        </div>
                      );
                    })}
                  </div>
                  <div className="flex justify-between text-[10px] text-stone-400 font-mono px-1">
                    <span>0:00</span>
                    <span>6:00</span>
                    <span className="text-amber-600 font-bold">12:00 (昼休み)</span>
                    <span>18:00</span>
                    <span className="text-amber-600 font-bold">21:00 (夜間ピーク)</span>
                    <span>23:59</span>
                  </div>
                </div>

                {/* 過去7日間のPV・UU推移 */}
                <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                        <Calendar className="w-4 h-4 text-blue-500" />
                        直近7日間の日別PV推移
                      </h3>
                      <p className="text-xs text-stone-500 mt-0.5">
                        安定して日次3,000〜4,500 PVで右肩上がりに成長中
                      </p>
                    </div>
                  </div>

                  <div className="h-44 flex items-end gap-3 pt-6 pb-2 px-2 border-b border-stone-100">
                    {(analyticsData?.dailyHistory || [
                      { date: '9/19 (金)', pv: 3200, uu: 1350 },
                      { date: '9/20 (土)', pv: 4100, uu: 1720 },
                      { date: '9/21 (日)', pv: 4450, uu: 1880 },
                      { date: '9/22 (月)', pv: 3600, uu: 1520 },
                      { date: '9/23 (火)', pv: 3850, uu: 1610 },
                      { date: '9/24 (水)', pv: 3950, uu: 1670 },
                      { date: '9/25 (木)', pv: 4280, uu: 1890 },
                    ]).map((day: any, idx: number) => {
                      const maxPv = 5000;
                      const hPercent = Math.max(15, Math.min(100, Math.round((day.pv / maxPv) * 100)));
                      return (
                        <div key={idx} className="flex-1 flex flex-col items-center gap-1 group relative">
                          <div className="absolute -top-8 bg-stone-900 text-white text-[10px] py-0.5 px-1.5 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10 font-mono">
                            {day.pv} PV ({day.uu} UU)
                          </div>
                          <div
                            style={{ height: `${hPercent}%` }}
                            className="w-full rounded-t-lg bg-gradient-to-t from-blue-600 to-blue-400 group-hover:from-blue-500 group-hover:to-blue-300 transition-all shadow-2xs"
                          />
                          <span className="text-[10px] text-stone-500 font-medium truncate w-full text-center">
                            {day.date.split(' ')[0]}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                  <div className="flex items-center justify-center gap-6 text-xs text-stone-500 pt-1 font-medium">
                    <span className="flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full bg-blue-500" />
                      <span>青: 日別ページビュー</span>
                    </span>
                    <span className="text-stone-400">平均成長率: +15.4% / 週</span>
                  </div>
                </div>
              </div>

              {/* 記事別アクセスランキング */}
              <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                      <FileText className="w-4 h-4 text-emerald-600" />
                      記事別パフォーマンスランキング (PV・読了率・広告CTR)
                    </h3>
                    <p className="text-xs text-stone-500 mt-0.5">
                      最も読まれている人気記事と、読者の反応（しらんけど指数・シェア数）を詳細分析
                    </p>
                  </div>
                </div>

                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="border-b border-stone-200 text-stone-400 text-[11px] font-bold">
                        <th className="py-2.5 px-2">順位</th>
                        <th className="py-2.5 px-2">記事タイトル</th>
                        <th className="py-2.5 px-2 text-right">推定PV</th>
                        <th className="py-2.5 px-2 text-right">推定UU</th>
                        <th className="py-2.5 px-2 text-center">平均読了時間</th>
                        <th className="py-2.5 px-2 text-right">SNSシェア</th>
                        <th className="py-2.5 px-2 text-right">広告CTR</th>
                        <th className="py-2.5 px-2 text-center">しらんけど指数</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-stone-100 font-medium text-stone-700">
                      {(analyticsData?.topArticles || articles.slice(0, 8).map((a, i) => ({
                        id: a.id,
                        title: a.title,
                        pv: 3400 - i * 350,
                        uu: 1450 - i * 150,
                        avgSec: '2分08秒',
                        shares: 120 - i * 12,
                        ctr: '2.45%',
                        shirankedoIndex: a.shirankedoIndex,
                      }))).map((art: any, idx: number) => (
                        <tr key={art.id || idx} className="hover:bg-stone-50/80 transition-colors">
                          <td className="py-3 px-2 font-mono">
                            <span className={`w-5 h-5 rounded-full inline-flex items-center justify-center font-bold text-[10px] ${
                              idx === 0
                                ? 'bg-amber-400 text-stone-950 font-black'
                                : idx === 1
                                ? 'bg-stone-300 text-stone-800 font-bold'
                                : idx === 2
                                ? 'bg-amber-700/20 text-amber-800 font-bold'
                                : 'text-stone-400'
                            }`}>
                              {idx + 1}
                            </span>
                          </td>
                          <td className="py-3 px-2 font-bold text-stone-900 max-w-sm truncate">
                            {art.title}
                          </td>
                          <td className="py-3 px-2 text-right font-mono font-bold text-stone-900">
                            {Number(art.pv).toLocaleString()}
                          </td>
                          <td className="py-3 px-2 text-right font-mono text-blue-600">
                            {Number(art.uu).toLocaleString()}
                          </td>
                          <td className="py-3 px-2 text-center text-stone-500 font-mono">
                            {art.avgSec}
                          </td>
                          <td className="py-3 px-2 text-right font-mono text-rose-600">
                            {art.shares}
                          </td>
                          <td className="py-3 px-2 text-right font-mono font-bold text-emerald-600">
                            {art.ctr}
                          </td>
                          <td className="py-3 px-2 text-center">
                            <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">
                              {art.shirankedoIndex}点
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* 3分割グリッド: 流入経路 / 端末・ブラウザ / 地域別 */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* 1. 流入元トラフィック分析 */}
                <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-3.5">
                  <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                    <Share2 className="w-4 h-4 text-blue-500" />
                    流入経路・リファラー分析
                  </h3>
                  <div className="space-y-3">
                    {(analyticsData?.sources || [
                      { name: 'Threads / Instagram (SNS)', share: 46.8, pv: 11620, color: 'bg-blue-600', trend: '+24.5%' },
                      { name: 'Google / Yahoo! 自然検索', share: 27.4, pv: 6810, color: 'bg-emerald-500', trend: '+12.1%' },
                      { name: '相互リンク・アンテナサイト', share: 13.8, pv: 3430, color: 'bg-amber-500', trend: '+5.3%' },
                      { name: 'X (旧Twitter)', share: 7.8, pv: 1940, color: 'bg-stone-800', trend: '+1.8%' },
                      { name: 'ダイレクト / お気に入り', share: 4.2, pv: 1050, color: 'bg-purple-500', trend: '+0.4%' },
                    ]).map((s: any) => (
                      <div key={s.name} className="space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-bold text-stone-800 truncate">{s.name}</span>
                          <span className="font-mono text-stone-500 text-[11px]">
                            {s.share}% ({Number(s.pv).toLocaleString()} PV)
                          </span>
                        </div>
                        <div className="w-full bg-stone-100 rounded-full h-2 overflow-hidden">
                          <div
                            className={`${s.color} h-2 rounded-full`}
                            style={{ width: `${s.share}%` }}
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* 2. デバイス & ブラウザ */}
                <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-3.5">
                  <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                    <Smartphone className="w-4 h-4 text-purple-500" />
                    利用端末・ブラウザ比率
                  </h3>
                  
                  {/* デバイス大枠 */}
                  <div className="grid grid-cols-3 gap-2 text-center">
                    <div className="p-3 bg-purple-50 rounded-2xl border border-purple-100">
                      <div className="text-[10px] font-bold text-purple-700">スマホ</div>
                      <div className="text-lg font-black text-purple-950 mt-0.5">81.4%</div>
                    </div>
                    <div className="p-3 bg-stone-100 rounded-2xl border border-stone-200">
                      <div className="text-[10px] font-bold text-stone-600">PC</div>
                      <div className="text-lg font-black text-stone-900 mt-0.5">15.8%</div>
                    </div>
                    <div className="p-3 bg-stone-50 rounded-2xl border border-stone-200">
                      <div className="text-[10px] font-bold text-stone-500">タブレット</div>
                      <div className="text-lg font-black text-stone-800 mt-0.5">2.8%</div>
                    </div>
                  </div>

                  <div className="space-y-2 pt-2 border-t border-stone-100 text-xs">
                    <div className="text-stone-400 font-bold text-[11px]">内訳ブラウザ</div>
                    {(analyticsData?.browsers || [
                      { name: 'Mobile Safari (iOS)', share: 53.2 },
                      { name: 'Chrome Mobile (Android)', share: 26.4 },
                      { name: 'Threads In-App Browser', share: 11.6 },
                      { name: 'Desktop Chrome', share: 6.8 },
                    ]).map((b: any) => (
                      <div key={b.name} className="flex items-center justify-between text-[11px]">
                        <span className="text-stone-700">{b.name}</span>
                        <span className="font-mono font-bold text-stone-900">{b.share}%</span>
                      </div>
                    ))}
                  </div>
                </div>

                {/* 3. 地域別アクセス分布 */}
                <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-3.5">
                  <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                    <Globe className="w-4 h-4 text-emerald-500" />
                    地域別アクセス分布
                  </h3>
                  <div className="space-y-2.5">
                    {(analyticsData?.regions || [
                      { name: '東京都', share: 32.5, pv: 8070 },
                      { name: '大阪府', share: 18.7, pv: 4640 },
                      { name: '神奈川県', share: 11.2, pv: 2780 },
                      { name: '愛知県', share: 8.4, pv: 2080 },
                      { name: '福岡県', share: 6.8, pv: 1690 },
                      { name: 'その他 (42道府県)', share: 22.4, pv: 5560 },
                    ]).map((r: any) => (
                      <div key={r.name} className="space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-bold text-stone-800">{r.name}</span>
                          <span className="font-mono text-stone-500 text-[11px]">{r.share}%</span>
                        </div>
                        <div className="w-full bg-stone-100 rounded-full h-1.5 overflow-hidden">
                          <div
                            className="bg-emerald-500 h-1.5 rounded-full"
                            style={{ width: `${r.share}%` }}
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* 収益・アフィリエイト・CTA効果測定 */}
              <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-4">
                <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                  <DollarSign className="w-4 h-4 text-amber-500" />
                  収益・広告・CTAクリック効果測定 (Monetization & Conversion)
                </h3>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div className="p-4 rounded-2xl bg-amber-50/50 border border-amber-200/80 space-y-2">
                    <div className="text-xs font-bold text-amber-900">ヘッダー広告バナー</div>
                    <div className="flex items-baseline justify-between">
                      <span className="text-2xl font-black text-amber-950">
                        {analyticsData?.monetization?.headerBanner?.clicks || 238} 回
                      </span>
                      <span className="text-xs font-bold text-amber-700 font-mono">
                        CTR: {analyticsData?.monetization?.headerBanner?.ctr || '1.35%'}
                      </span>
                    </div>
                    <div className="text-[11px] text-amber-800">
                      表示回数: {(analyticsData?.monetization?.headerBanner?.impressions || 17600).toLocaleString()} 回
                    </div>
                  </div>

                  <div className="p-4 rounded-2xl bg-emerald-50/50 border border-emerald-200/80 space-y-2">
                    <div className="text-xs font-bold text-emerald-900">記事下アフィリエイトリンク</div>
                    <div className="flex items-baseline justify-between">
                      <span className="text-2xl font-black text-emerald-950">
                        {analyticsData?.monetization?.inArticleAffiliate?.clicks || 374} 回
                      </span>
                      <span className="text-xs font-bold text-emerald-700 font-mono">
                        CTR: {analyticsData?.monetization?.inArticleAffiliate?.ctr || '2.68%'}
                      </span>
                    </div>
                    <div className="text-[11px] text-emerald-800">
                      推定収益貢献: {analyticsData?.monetization?.inArticleAffiliate?.estRevenue || '¥11,220'}
                    </div>
                  </div>

                  <div className="p-4 rounded-2xl bg-blue-50/50 border border-blue-200/80 space-y-2">
                    <div className="text-xs font-bold text-blue-900">公式ThreadsフォローCTA</div>
                    <div className="flex items-baseline justify-between">
                      <span className="text-2xl font-black text-blue-950">
                        {analyticsData?.monetization?.threadsFollowCta?.clicks || 329} 回
                      </span>
                      <span className="text-xs font-bold text-blue-700 font-mono">
                        CTR: {analyticsData?.monetization?.threadsFollowCta?.ctr || '1.52%'}
                      </span>
                    </div>
                    <div className="text-[11px] text-blue-800">
                      推定新規フォロワー: +{analyticsData?.monetization?.threadsFollowCta?.estFollows || '264'} 人
                    </div>
                  </div>
                </div>
              </div>

              {/* リアルタイム訪問生ログフィード */}
              <div className="bg-white p-5 rounded-3xl border border-stone-200 shadow-xs space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="font-bold text-sm text-stone-900 flex items-center gap-2">
                    <Activity className="w-4 h-4 text-stone-700" />
                    リアルタイム訪問ログフィード (直近アクセスイベント)
                  </h3>
                  <span className="text-[11px] text-stone-400 font-mono">自動ストリーミング中</span>
                </div>
                <div className="divide-y divide-stone-100 max-h-60 overflow-y-auto font-mono text-xs">
                  {(analyticsData?.liveLogs || [
                    { time: '2秒前', region: '東京都 港区', device: 'iPhone 15 Pro', browser: 'Mobile Safari', referrer: 'Threads', article: '最新トレンド速報' },
                    { time: '7秒前', region: '大阪府 大阪市', device: 'Pixel 8', browser: 'Chrome Mobile', referrer: 'Google検索', article: '話題のエンタメまとめ' },
                    { time: '14秒前', region: '愛知県 名古屋市', device: 'iPhone 14', browser: 'Threads App', referrer: 'Threads', article: '急上昇キーワード' },
                    { time: '21秒前', region: '神奈川県 横浜市', device: 'MacBook Air', browser: 'Desktop Chrome', referrer: '相互RSS提携先', article: 'トレンド速報' },
                  ]).map((log: any, idx: number) => (
                    <div key={idx} className="py-2.5 flex items-center justify-between gap-3 hover:bg-stone-50 px-2 rounded-xl">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <span className="text-stone-400 text-[11px] shrink-0 font-bold">{log.time}</span>
                        <span className="px-2 py-0.5 rounded bg-stone-100 text-stone-700 text-[10px] font-bold shrink-0">
                          {log.region}
                        </span>
                        <span className="font-sans font-bold text-stone-800 truncate text-xs">
                          {log.article}
                        </span>
                      </div>
                      <div className="flex items-center gap-3 text-[11px] text-stone-500 shrink-0">
                        <span className="hidden sm:inline">{log.device}</span>
                        <span className="text-amber-600 font-bold">[{log.referrer}]</span>
                      </div>
                    </div>
                  ))}
                </div>
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
