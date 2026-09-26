import React, { useState, useEffect } from 'react';
import { Flame, TrendingUp, Sparkles, Filter, ShieldCheck, ChevronRight, BookOpen, ShieldAlert } from 'lucide-react';
import { Site, Article, Category, TrendCandidate } from './types';
import { Header } from './components/Header';
import { ArticleCard } from './components/ArticleCard';
import { ArticleDetailModal } from './components/ArticleDetailModal';
import { ShirankedoAboutModal } from './components/ShirankedoAboutModal';
import { CommentRulesModal } from './components/CommentRulesModal';
import { AdminConsole } from './components/AdminConsole';
import { PageModal } from './components/PageModal';

export default function App() {
  const [sites, setSites] = useState<Site[]>([]);
  const [currentSite, setCurrentSite] = useState<Site>({
    id: 1,
    subdomain: '',
    name: 'しらんけど',
    description: '「いま日本で何が話題か」を自動分析するトレンドサイト。しらんけど。',
    genre: 'general',
    isPublic: true,
    allowAutoPublish: true,
    youtubeThumbnailEnabled: true,
  });

  const [categories, setCategories] = useState<Category[]>([]);
  const [articles, setArticles] = useState<Article[]>([]);
  const [trends, setTrends] = useState<TrendCandidate[]>([]);
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [selectedSort, setSelectedSort] = useState<'recent' | 'index' | 'rapid'>('recent');

  const [selectedArticle, setSelectedArticle] = useState<Article | null>(null);
  const [activeView, setActiveView] = useState<'feed' | 'about' | 'rules' | 'admin'>('feed');
  const [pageSlug, setPageSlug] = useState<'about' | 'privacy-policy' | 'que' | null>(null);

  // Load sites
  const fetchSites = () => {
    fetch('/api/sites')
      .then((res) => res.json())
      .then((data) => {
        if (data.sites) setSites(data.sites);
        if (data.current) setCurrentSite(data.current);
      })
      .catch(console.error);
  };

  useEffect(() => {
    fetchSites();
  }, []);

  // Load categories and trends when site changes
  useEffect(() => {
    fetch(`/api/categories?site_id=${currentSite.id}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.categories) setCategories(data.categories);
      })
      .catch(console.error);

    fetch(`/api/trends?site_id=${currentSite.id}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.trends) setTrends(data.trends);
      })
      .catch(console.error);
  }, [currentSite.id]);

  // Load articles when site, category, or sort changes
  useEffect(() => {
    const url = new URL('/api/articles', window.location.origin);
    url.searchParams.set('site_id', currentSite.id.toString());
    if (selectedCategory !== 'all') {
      url.searchParams.set('category', selectedCategory);
    }
    if (selectedSort !== 'recent') {
      url.searchParams.set('sort', selectedSort);
    }

    fetch(url.toString())
      .then((res) => res.json())
      .then((data) => {
        if (data.articles) setArticles(data.articles);
      })
      .catch(console.error);
  }, [currentSite.id, selectedCategory, selectedSort]);

  // Switch site
  const handleSelectSite = (siteId: number) => {
    const target = sites.find((s) => s.id === siteId);
    if (target) {
      setCurrentSite(target);
      setSelectedCategory('all');
      setSelectedSort('recent');
    }
  };

  const hotTicker = articles.length > 0
    ? `${articles[0].title}（しらんけど指数: ${articles[0].shirankedoIndex}点）`
    : '最新トレンドを全自動収集・事実確認中。しらんけど。';

  return (
    <div className="min-h-screen bg-stone-100 text-stone-900 font-sans flex flex-col selection:bg-amber-200 selection:text-stone-900">
      {/* Header */}
      <Header
        currentSite={currentSite}
        sites={sites}
        onSelectSite={handleSelectSite}
        activeView={activeView}
        onNavigate={(view) => setActiveView(view)}
        hotTrendTicker={hotTicker}
      />

      {/* ADMIN VIEW */}
      {activeView === 'admin' ? (
        <AdminConsole
          currentSite={currentSite}
          sites={sites}
          onSelectSite={handleSelectSite}
          onRefreshSites={fetchSites}
          onViewSite={() => setActiveView('feed')}
        />
      ) : (
        /* MAIN FEED VIEW */
        <main className="max-w-6xl mx-auto px-4 py-6 w-full flex-1 space-y-8">
          {/* TOP TRENDS RANKING WIDGET (今日のしらんけど指数 TOP 10) */}
          <section className="bg-white rounded-3xl border border-stone-200 p-5 sm:p-6 shadow-xs space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 pb-3">
              <div className="flex items-center gap-2.5">
                <div className="w-7 h-7 rounded-lg bg-amber-500 flex items-center justify-center text-stone-950 font-black text-xs">
                  TOP
                </div>
                <div>
                  <h2 className="text-base sm:text-lg font-black text-stone-900 flex items-center gap-2">
                    今日の「しらんけど指数」ランキング
                  </h2>
                  <p className="text-xs text-stone-500">
                    検索・SNS・動画・ニュースを横断して算出した最新の話題度指標
                  </p>
                </div>
              </div>

              <button
                onClick={() => setActiveView('about')}
                className="text-xs font-bold text-amber-700 hover:text-amber-900 flex items-center gap-1 transition-colors"
              >
                <span>指数とは？</span>
                <ChevronRight className="w-3.5 h-3.5" />
              </button>
            </div>

            {/* Ranking Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              {articles.slice(0, 6).map((item, idx) => (
                <div
                  key={item.id}
                  onClick={() => setSelectedArticle(item)}
                  className="p-3 rounded-2xl bg-stone-50 hover:bg-amber-50/60 border border-stone-200 hover:border-amber-300 transition-all cursor-pointer flex items-center justify-between gap-3 group"
                >
                  <div className="flex items-center gap-3 truncate">
                    <span className={`w-6 h-6 rounded-md flex items-center justify-center font-black text-xs shrink-0 ${
                      idx === 0
                        ? 'bg-amber-500 text-stone-950'
                        : idx === 1
                        ? 'bg-stone-300 text-stone-800'
                        : idx === 2
                        ? 'bg-amber-700 text-amber-100'
                        : 'bg-stone-200 text-stone-600'
                    }`}>
                      {idx + 1}
                    </span>
                    <span className="text-xs font-bold text-stone-800 group-hover:text-stone-950 truncate">
                      {item.title}
                    </span>
                  </div>

                  <div className="flex items-center gap-1.5 shrink-0">
                    {item.isRapidRise && (
                      <Flame className="w-3.5 h-3.5 fill-red-500 text-red-500" />
                    )}
                    <span className="text-xs font-black text-stone-900 font-mono">
                      {item.shirankedoIndex}
                      <span className="text-[10px] text-stone-400 font-normal">点</span>
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </section>

          {/* FILTER & CATEGORY BAR */}
          <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
            {/* Category Tabs */}
            <div className="flex items-center gap-1.5 overflow-x-auto py-1 max-w-full">
              <button
                onClick={() => setSelectedCategory('all')}
                className={`px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors whitespace-nowrap ${
                  selectedCategory === 'all'
                    ? 'bg-stone-900 text-white'
                    : 'bg-white text-stone-600 hover:bg-stone-200 border border-stone-200'
                }`}
              >
                すべて
              </button>
              {categories.filter((c) => c.slug !== 'all').map((cat) => (
                <button
                  key={cat.id}
                  onClick={() => setSelectedCategory(cat.slug)}
                  className={`px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors whitespace-nowrap ${
                    selectedCategory === cat.slug
                      ? 'bg-stone-900 text-white'
                      : 'bg-white text-stone-600 hover:bg-stone-200 border border-stone-200'
                  }`}
                >
                  {cat.name}
                </button>
              ))}
            </div>

            {/* Sort Filter Buttons */}
            <div className="flex items-center gap-1 bg-white p-1 rounded-xl border border-stone-200 shadow-2xs">
              <button
                onClick={() => setSelectedSort('recent')}
                className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-colors ${
                  selectedSort === 'recent'
                    ? 'bg-stone-100 text-stone-900 font-bold'
                    : 'text-stone-500 hover:text-stone-900'
                }`}
              >
                新着順
              </button>
              <button
                onClick={() => setSelectedSort('rapid')}
                className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-colors flex items-center gap-1 ${
                  selectedSort === 'rapid'
                    ? 'bg-red-50 text-red-700 font-bold'
                    : 'text-stone-500 hover:text-red-700'
                }`}
              >
                <Flame className="w-3 h-3 text-red-500" />
                急上昇順
              </button>
              <button
                onClick={() => setSelectedSort('index')}
                className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-colors ${
                  selectedSort === 'index'
                    ? 'bg-amber-50 text-amber-900 font-bold'
                    : 'text-stone-500 hover:text-amber-900'
                }`}
              >
                指数順
              </button>
            </div>
          </div>

          {/* ARTICLES FEED */}
          <div className="space-y-4">
            {articles.length > 0 ? (
              articles.map((art) => (
                <ArticleCard
                  key={art.id}
                  article={art}
                  onSelect={(a) => setSelectedArticle(a)}
                />
              ))
            ) : (
              <div className="text-center py-16 bg-white rounded-3xl border border-stone-200 space-y-2">
                <p className="text-stone-500 text-sm font-medium">
                  該当するトレンド記事はありません。
                </p>
                <button
                  onClick={() => {
                    setSelectedCategory('all');
                    setSelectedSort('recent');
                  }}
                  className="text-xs font-bold text-amber-700 hover:underline"
                >
                  フィルターをリセット
                </button>
              </div>
            )}
          </div>
        </main>
      )}

      {/* ARTICLE DETAIL MODAL */}
      {selectedArticle && (
        <ArticleDetailModal
          article={selectedArticle}
          onClose={() => setSelectedArticle(null)}
          onOpenRules={() => {
            setSelectedArticle(null);
            setActiveView('rules');
          }}
        />
      )}

      {/* STATIC ABOUT MODAL */}
      {activeView === 'about' && (
        <ShirankedoAboutModal onClose={() => setActiveView('feed')} />
      )}

      {/* STATIC RULES MODAL */}
      {activeView === 'rules' && (
        <CommentRulesModal onClose={() => setActiveView('feed')} />
      )}

      {/* PAGE MODAL (about / privacy-policy / que) */}
      {pageSlug && (
        <PageModal
          slug={pageSlug}
          onClose={() => setPageSlug(null)}
          onSwitchSlug={(newSlug) => setPageSlug(newSlug)}
        />
      )}

      {/* FOOTER */}
      <footer className="bg-stone-900 text-stone-400 mt-16 border-t border-stone-800 text-xs py-10 px-4">
        <div className="max-w-6xl mx-auto space-y-6">
          <div className="flex flex-wrap items-center justify-between gap-4 border-b border-stone-800 pb-6">
            <div className="space-y-1">
              <div className="text-white font-black text-lg tracking-tight flex items-center gap-2">
                <span>{currentSite.name}</span>
                <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 font-mono">
                  PHP+MySQL Architecture
                </span>
              </div>
              <p className="text-stone-400 text-xs max-w-lg">
                インターネット上の話題を客観集計し、一次ソースの確認を経て要約するトレンドメディアシステムです。
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-3 sm:gap-4 text-xs font-medium">
              <button
                onClick={() => setPageSlug('about')}
                className="hover:text-amber-400 text-stone-300 font-bold transition-colors"
              >
                サイトについて
              </button>
              <span>•</span>
              <button
                onClick={() => setPageSlug('privacy-policy')}
                className="hover:text-amber-400 text-stone-300 font-bold transition-colors"
              >
                プライバシーポリシー
              </button>
              <span>•</span>
              <button
                onClick={() => setPageSlug('que')}
                className="hover:text-amber-400 text-stone-300 font-bold transition-colors"
              >
                お問い合わせ
              </button>
              <span>•</span>
              <button
                onClick={() => setActiveView('about')}
                className="hover:text-white transition-colors"
              >
                しらんけど指数とは？
              </button>
              <span>•</span>
              <button
                onClick={() => setActiveView('rules')}
                className="hover:text-white transition-colors"
              >
                利用ルール
              </button>
              <span>•</span>
              <button
                onClick={() => setActiveView('admin')}
                className="text-amber-400 hover:text-amber-300 transition-colors font-bold"
              >
                管理画面
              </button>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-stone-400">
            <div>
              &copy; 2026 {currentSite.name} All Rights Reserved.
            </div>
            <div className="italic text-stone-400 font-serif">
              「客観的な事実をもとに整理していますが、最終的な判断は各自で行ってください。しらんけど。」
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
