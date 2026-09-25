import React from 'react';
import { Sparkles, Flame, ShieldAlert, BookOpen, Settings, ExternalLink, Globe } from 'lucide-react';
import { Site } from '../types';

interface HeaderProps {
  currentSite: Site;
  sites: Site[];
  onSelectSite: (siteId: number) => void;
  activeView: 'feed' | 'about' | 'rules' | 'admin';
  onNavigate: (view: 'feed' | 'about' | 'rules' | 'admin') => void;
  hotTrendTicker?: string;
}

export const Header: React.FC<HeaderProps> = ({
  currentSite,
  sites,
  onSelectSite,
  activeView,
  onNavigate,
  hotTrendTicker = '千鳥・大悟の新番組独占配信が決定！公式PVに歓喜の声（しらんけど指数: 88）',
}) => {
  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-stone-200 shadow-xs">
      {/* Top Status & Hot Trend Ticker Bar */}
      <div className="bg-stone-900 text-stone-200 px-4 py-1.5 text-xs">
        <div className="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2 overflow-hidden text-stone-300">
            <span className="w-2 h-2 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
            <span className="text-[11px] font-bold text-amber-400 uppercase tracking-wider shrink-0">HOT TREND</span>
            <span className="text-stone-300 truncate text-[11px] sm:text-xs">
              {hotTrendTicker}
            </span>
          </div>
          <div className="hidden md:flex items-center gap-3 text-stone-400">
            <span>しらんけど v2.4</span>
            <span>|</span>
            <span className="text-emerald-400 flex items-center gap-1">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
              自動パイプライン稼働中
            </span>
          </div>
        </div>
      </div>

      {/* Main Navigation Bar */}
      <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        {/* Brand Logo */}
        <div 
          onClick={() => onNavigate('feed')}
          className="cursor-pointer flex items-center gap-2.5 group"
        >
          <div className="w-10 h-10 rounded-xl bg-amber-500 flex items-center justify-center text-stone-950 font-black text-xl tracking-tighter shadow-sm group-hover:scale-105 transition-transform">
            知
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="text-2xl font-black tracking-tight text-stone-900 font-sans">
                {currentSite.name}
              </span>
              <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300 font-bold tracking-wider">
                公式自動システム
              </span>
            </div>
            <p className="text-xs text-stone-500 line-clamp-1">
              {currentSite.description}
            </p>
          </div>
        </div>

        {/* Action Buttons */}
        <nav className="flex items-center gap-2">
          <button
            onClick={() => onNavigate('feed')}
            className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
              activeView === 'feed'
                ? 'bg-stone-100 text-stone-900 font-bold'
                : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50'
            }`}
          >
            最新トレンド
          </button>

          <button
            onClick={() => onNavigate('about')}
            className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5 ${
              activeView === 'about'
                ? 'bg-amber-50 text-amber-900 font-bold border border-amber-200'
                : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50'
            }`}
          >
            <BookOpen className="w-4 h-4 text-amber-600" />
            <span className="hidden sm:inline">しらんけど指数とは？</span>
          </button>

          <button
            onClick={() => onNavigate('rules')}
            className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5 ${
              activeView === 'rules'
                ? 'bg-rose-50 text-rose-900 font-bold border border-rose-200'
                : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50'
            }`}
          >
            <ShieldAlert className="w-4 h-4 text-rose-600" />
            <span className="hidden sm:inline">利用ルール</span>
          </button>

          <button
            onClick={() => onNavigate('admin')}
            className={`ml-1 px-3 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5 ${
              activeView === 'admin'
                ? 'bg-stone-900 text-white shadow-xs'
                : 'bg-stone-800 text-stone-100 hover:bg-stone-900'
            }`}
          >
            <Settings className="w-4 h-4 text-amber-400" />
            <span>管理画面</span>
          </button>
        </nav>
      </div>

      {/* Live Trend Ticker Banner */}
      <div className="bg-stone-100 border-t border-stone-200 px-4 py-1.5 text-xs text-stone-700 flex items-center gap-2 overflow-hidden">
        <span className="bg-red-600 text-white text-[11px] font-bold px-2 py-0.5 rounded flex items-center gap-1 shrink-0">
          <Flame className="w-3.5 h-3.5 animate-bounce" />
          NOW
        </span>
        <div className="truncate font-medium text-stone-800">
          {hotTrendTicker}
        </div>
      </div>
    </header>
  );
};
