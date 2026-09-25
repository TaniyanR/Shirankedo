import React, { useState, useEffect } from 'react';
import { 
  X, Clock, ExternalLink, ShieldCheck, ThumbsUp, HelpCircle, 
  TrendingUp, MessageSquare, AlertTriangle, Send, CheckCircle2, ShieldAlert
} from 'lucide-react';
import { Article, ArticleSource, Comment } from '../types';
import { ShirankedoGauge } from './ShirankedoGauge';

interface ArticleDetailModalProps {
  article: Article;
  onClose: () => void;
  onOpenRules: () => void;
}

export const ArticleDetailModal: React.FC<ArticleDetailModalProps> = ({
  article,
  onClose,
  onOpenRules,
}) => {
  const [sources, setSources] = useState<ArticleSource[]>(article.sources || []);
  const [votes, setVotes] = useState(
    article.votes || { knew: 40, didntKnow: 60, grow: 70, end: 30 }
  );
  const [hasVotedKnew, setHasVotedKnew] = useState(false);
  const [hasVotedGrowth, setHasVotedGrowth] = useState(false);

  const [comments, setComments] = useState<Comment[]>([]);
  const [newCommentText, setNewCommentText] = useState('');
  const [commentError, setCommentError] = useState<string | null>(null);
  const [commentSuccess, setCommentSuccess] = useState(false);
  const [isSubmittingComment, setIsSubmittingComment] = useState(false);

  // Fetch full details and comments
  useEffect(() => {
    fetch(`/api/articles/${article.id}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.article) {
          if (data.article.sources) setSources(data.article.sources);
          if (data.article.votes) setVotes(data.article.votes);
        }
      })
      .catch(console.error);

    fetch(`/api/articles/${article.id}/comments`)
      .then((res) => res.json())
      .then((data) => {
        if (data.comments) setComments(data.comments);
      })
      .catch(console.error);
  }, [article.id]);

  // Handle voting
  const handleVote = async (pollType: 'knew_ratio' | 'future_growth', voteValue: string) => {
    if (pollType === 'knew_ratio' && hasVotedKnew) return;
    if (pollType === 'future_growth' && hasVotedGrowth) return;

    try {
      const res = await fetch(`/api/articles/${article.id}/vote`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pollType, voteValue }),
      });
      const data = await res.json();
      if (data.success && data.votes) {
        setVotes(data.votes);
        if (pollType === 'knew_ratio') setHasVotedKnew(true);
        if (pollType === 'future_growth') setHasVotedGrowth(true);
      }
    } catch (err) {
      console.error('Vote failed:', err);
    }
  };

  // Handle comment submission
  const handleSubmitComment = async (e: React.FormEvent) => {
    e.preventDefault();
    setCommentError(null);
    setCommentSuccess(false);

    if (!newCommentText.trim()) {
      setCommentError('コメントを入力してください。');
      return;
    }

    setIsSubmittingComment(true);
    try {
      const res = await fetch(`/api/articles/${article.id}/comments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content: newCommentText }),
      });
      const data = await res.json();

      if (!res.ok || !data.success) {
        setCommentError(data.error || 'コメントの投稿に失敗しました。');
      } else {
        setComments([data.comment, ...comments]);
        setNewCommentText('');
        setCommentSuccess(true);
        setTimeout(() => setCommentSuccess(false), 4000);
      }
    } catch (err: any) {
      setCommentError('通信エラーが発生しました。');
    } finally {
      setIsSubmittingComment(false);
    }
  };

  // Vote calculation helpers
  const totalKnewVotes = votes.knew + votes.didntKnow;
  const knewPct = totalKnewVotes > 0 ? Math.round((votes.knew / totalKnewVotes) * 100) : 50;
  const didntKnowPct = 100 - knewPct;

  const totalGrowthVotes = votes.grow + votes.end;
  const growPct = totalGrowthVotes > 0 ? Math.round((votes.grow / totalGrowthVotes) * 100) : 50;
  const endPct = 100 - growPct;

  const displayBody = article.body || (article as any).content || '詳細情報を読み込み中...しらんけど。';
  const displayImage = article.imageUrl || (article as any).thumbnailUrl;
  const displayCategory = article.categoryName || (article as any).category || '総合';
  const displayWhy = article.whyTrending || (article as any).objectiveFact || displayBody;
  const displayConclusion = article.conclusionSentence || (article as any).conclusion || '…まあ、真相は知らんけどな！';

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-stone-950/70 backdrop-blur-xs flex justify-center p-2 sm:p-4 md:py-8">
      <div className="relative bg-white w-full max-w-3xl rounded-3xl shadow-2xl border border-stone-200 overflow-hidden flex flex-col my-auto max-h-[95vh]">
        {/* Modal Top Bar */}
        <div className="sticky top-0 z-10 bg-white/95 backdrop-blur-md px-6 py-4 border-b border-stone-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold px-2.5 py-1 rounded-md bg-stone-100 text-stone-700">
              {displayCategory}
            </span>
            <span className="text-xs text-stone-500">
              {article.publishedAt ? new Date(article.publishedAt).toLocaleDateString('ja-JP', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
              }) : '最新'}
            </span>
          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-full hover:bg-stone-100 text-stone-500 hover:text-stone-900 transition-colors"
            aria-label="閉じる"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Scrollable Modal Body */}
        <div className="overflow-y-auto p-5 sm:p-7 space-y-6">
          {/* Article Title */}
          <h1 className="text-2xl sm:text-3xl font-black text-stone-950 leading-tight">
            {article.title}
          </h1>

          {/* Shirankedo Gauge Card */}
          <ShirankedoGauge
            score={article.shirankedoIndex || 85}
            label={article.indexLabel || '話題'}
            isRapidRise={article.isRapidRise}
            growthRate={article.growthRate || 120}
            firstDetectedAt={article.firstDetectedAt || new Date().toISOString()}
            size="lg"
          />

          {/* Hero Image */}
          {displayImage && (
            <div className="rounded-2xl overflow-hidden border border-stone-200 bg-stone-100 max-h-96">
              <img
                src={displayImage}
                alt={article.title}
                referrerPolicy="no-referrer"
                className="w-full h-full object-cover"
              />
            </div>
          )}

          {/* Why Trending Box */}
          {displayWhy && (
            <div className="bg-amber-50/80 border-l-4 border-amber-500 p-4 rounded-r-xl space-y-1">
              <div className="text-xs font-black text-amber-900 uppercase tracking-wide">
                【なぜ話題？】
              </div>
              <p className="text-sm font-medium text-stone-800 leading-relaxed">
                {displayWhy}
              </p>
            </div>
          )}

          {/* Main Factual Body */}
          <div className="space-y-4 text-stone-800 text-base leading-relaxed font-sans">
            {displayBody.split('\n\n').map((para, i) => (
              <p key={i} className="text-justify">
                {para}
              </p>
            ))}
          </div>

          {/* YouTube Video Embed (if available) */}
          {article.youtubeVideoId && (
            <div className="rounded-2xl overflow-hidden border border-stone-200 aspect-video bg-black">
              <iframe
                src={`https://www.youtube-nocookie.com/embed/${article.youtubeVideoId}`}
                title="公式関連映像"
                className="w-full h-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
              />
            </div>
          )}

          {/* Trademark Conclusion Sentence with 「〜しらんけど。」 */}
          <div className="bg-stone-900 text-white p-5 rounded-2xl space-y-1.5 shadow-md">
            <div className="text-[11px] font-bold text-amber-400 tracking-wider">
              所感・編集部メモ
            </div>
            <div className="text-base sm:text-lg font-bold font-serif leading-relaxed text-stone-100">
              {displayConclusion}
            </div>
          </div>

          {/* Primary Source References */}
          <div className="bg-stone-50 border border-stone-200 rounded-2xl p-4 sm:p-5 space-y-3">
            <div className="flex items-center gap-2 text-xs font-bold text-stone-700">
              <ShieldCheck className="w-4 h-4 text-emerald-600" />
              <span>参考・出典（確認済み一次情報源）</span>
            </div>
            <p className="text-xs text-stone-500">
              ※当サイトは憶測や噂を排除し、公式発表や大手報道機関の公表データをもとに事実関係を整理しています。
            </p>

            <div className="space-y-2 pt-1">
              {sources.length > 0 ? (
                sources.map((src, idx) => (
                  <a
                    key={idx}
                    href={src.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center justify-between p-2.5 rounded-xl bg-white border border-stone-200 hover:border-amber-400 hover:bg-amber-50/50 transition-colors text-xs text-stone-800 group"
                  >
                    <div className="flex items-center gap-2 truncate pr-2">
                      <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-stone-100 text-stone-600">
                        {src.sourceType === 'official' ? '公式発表' : src.sourceType === 'news' ? '報道機関' : '動画'}
                      </span>
                      <span className="font-medium truncate group-hover:text-amber-800">
                        {src.title}
                      </span>
                      <span className="text-stone-400 text-[11px]">({src.publisher})</span>
                    </div>
                    <ExternalLink className="w-3.5 h-3.5 text-stone-400 group-hover:text-amber-600 shrink-0" />
                  </a>
                ))
              ) : (
                <div className="text-xs text-stone-400 italic">公式プレスリリース等の公表資料参照</div>
              )}
            </div>
          </div>

          {/* Interactive User Polls */}
          <div className="border-t border-b border-stone-200 py-6 space-y-6">
            <h3 className="text-base font-black text-stone-900 flex items-center gap-2">
              <HelpCircle className="w-5 h-5 text-amber-500" />
              みんなのリアルタイムアンケート
            </h3>

            {/* Poll 1: 知ってた？ / 知らんかった？ */}
            <div className="bg-stone-50 p-4 rounded-2xl border border-stone-200 space-y-3">
              <div className="flex items-center justify-between text-xs font-bold text-stone-700">
                <span>この話題、知ってた？</span>
                <span className="text-stone-500">{totalKnewVotes} 人が回答</span>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <button
                  onClick={() => handleVote('knew_ratio', 'knew')}
                  disabled={hasVotedKnew}
                  className={`py-3 px-4 rounded-xl border text-sm font-bold flex flex-col items-center gap-1 transition-all ${
                    hasVotedKnew
                      ? 'bg-amber-100/70 border-amber-300 text-amber-900 cursor-default'
                      : 'bg-white border-stone-300 hover:border-amber-400 hover:bg-amber-50 text-stone-800 shadow-2xs'
                  }`}
                >
                  <span>知ってた！</span>
                  <span className="text-xs text-stone-500">{knewPct}% ({votes.knew})</span>
                </button>

                <button
                  onClick={() => handleVote('knew_ratio', 'didnt_know')}
                  disabled={hasVotedKnew}
                  className={`py-3 px-4 rounded-xl border text-sm font-bold flex flex-col items-center gap-1 transition-all ${
                    hasVotedKnew
                      ? 'bg-stone-200 border-stone-300 text-stone-800 cursor-default'
                      : 'bg-white border-stone-300 hover:border-stone-400 hover:bg-stone-100 text-stone-800 shadow-2xs'
                  }`}
                >
                  <span>知らんかった…</span>
                  <span className="text-xs text-stone-500">{didntKnowPct}% ({votes.didntKnow})</span>
                </button>
              </div>

              {/* Progress visualizer */}
              <div className="w-full bg-stone-200 h-2 rounded-full overflow-hidden flex">
                <div className="bg-amber-500 h-full transition-all" style={{ width: `${knewPct}%` }} />
                <div className="bg-stone-400 h-full transition-all" style={{ width: `${didntKnowPct}%` }} />
              </div>
            </div>

            {/* Poll 2: 今後もっと伸びる？ / もう終わる？ */}
            <div className="bg-stone-50 p-4 rounded-2xl border border-stone-200 space-y-3">
              <div className="flex items-center justify-between text-xs font-bold text-stone-700">
                <span>このトレンドの勢いは？</span>
                <span className="text-stone-500">{totalGrowthVotes} 人が回答</span>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <button
                  onClick={() => handleVote('future_growth', 'grow')}
                  disabled={hasVotedGrowth}
                  className={`py-3 px-4 rounded-xl border text-sm font-bold flex flex-col items-center gap-1 transition-all ${
                    hasVotedGrowth
                      ? 'bg-red-100/70 border-red-300 text-red-900 cursor-default'
                      : 'bg-white border-stone-300 hover:border-red-400 hover:bg-red-50 text-stone-800 shadow-2xs'
                  }`}
                >
                  <span>🔥 もっと伸びる</span>
                  <span className="text-xs text-stone-500">{growPct}% ({votes.grow})</span>
                </button>

                <button
                  onClick={() => handleVote('future_growth', 'end')}
                  disabled={hasVotedGrowth}
                  className={`py-3 px-4 rounded-xl border text-sm font-bold flex flex-col items-center gap-1 transition-all ${
                    hasVotedGrowth
                      ? 'bg-stone-200 border-stone-300 text-stone-800 cursor-default'
                      : 'bg-white border-stone-300 hover:border-stone-400 hover:bg-stone-100 text-stone-800 shadow-2xs'
                  }`}
                >
                  <span>💨 もう落ち着く</span>
                  <span className="text-xs text-stone-500">{endPct}% ({votes.end})</span>
                </button>
              </div>

              {/* Progress visualizer */}
              <div className="w-full bg-stone-200 h-2 rounded-full overflow-hidden flex">
                <div className="bg-red-500 h-full transition-all" style={{ width: `${growPct}%` }} />
                <div className="bg-stone-400 h-full transition-all" style={{ width: `${endPct}%` }} />
              </div>
            </div>
          </div>

          {/* User Comments & Anti-Defamation Policy */}
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-black text-stone-900 flex items-center gap-2">
                <MessageSquare className="w-5 h-5 text-stone-700" />
                みんなの感想 ({comments.length})
              </h3>
            </div>

            {/* Warning banner with rule modal trigger */}
            <div className="bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-950 flex items-start justify-between gap-3">
              <div className="flex items-start gap-2">
                <ShieldAlert className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                <div>
                  <span className="font-bold">コメント投稿の注意：</span>
                  個人への誹謗中傷、企業の営業妨害、個人情報の書き込みは禁止です。
                  <span className="block text-[11px] text-rose-700 mt-0.5">
                    ※文字（プレーンテキスト）のみ投稿可能です。URLやHTMLは自動遮断されます。
                  </span>
                </div>
              </div>
              <button
                onClick={onOpenRules}
                className="text-xs text-rose-700 hover:text-rose-950 font-bold underline shrink-0 whitespace-nowrap"
              >
                利用ルール確認
              </button>
            </div>

            {/* Comment Form */}
            <form onSubmit={handleSubmitComment} className="space-y-2">
              <div className="relative">
                <textarea
                  value={newCommentText}
                  onChange={(e) => setNewCommentText(e.target.value)}
                  placeholder="客観的な感想や補足情報を書き込む（文字のみ・最大400文字）"
                  maxLength={400}
                  rows={3}
                  className="w-full rounded-xl border border-stone-300 p-3 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-hidden"
                />
                <span className="absolute bottom-2.5 right-3 text-[11px] text-stone-400">
                  {newCommentText.length} / 400文字
                </span>
              </div>

              {commentError && (
                <div className="p-2.5 bg-red-100 border border-red-300 text-red-900 rounded-lg text-xs font-bold flex items-center gap-1.5">
                  <AlertTriangle className="w-4 h-4 text-red-600 shrink-0" />
                  <span>{commentError}</span>
                </div>
              )}

              {commentSuccess && (
                <div className="p-2.5 bg-emerald-100 border border-emerald-300 text-emerald-900 rounded-lg text-xs font-bold flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                  <span>コメントを投稿しました。健全な場へのご協力ありがとうございます。</span>
                </div>
              )}

              <div className="flex justify-end">
                <button
                  type="submit"
                  disabled={isSubmittingComment || !newCommentText.trim()}
                  className="px-5 py-2.5 rounded-xl bg-stone-900 hover:bg-stone-800 disabled:opacity-50 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs transition-colors"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>{isSubmittingComment ? '送信中...' : 'コメントを投稿'}</span>
                </button>
              </div>
            </form>

            {/* Comments List */}
            <div className="space-y-2.5 pt-2">
              {comments.length > 0 ? (
                comments.map((c) => (
                  <div key={c.id} className="p-3 bg-stone-50 border border-stone-200 rounded-xl space-y-1">
                    <div className="flex items-center justify-between text-[11px] text-stone-400">
                      <span className="font-mono text-stone-500">匿名ユーザー</span>
                      <span>
                        {new Date(c.createdAt).toLocaleDateString('ja-JP', {
                          month: 'numeric',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </div>
                    <p className="text-sm text-stone-800 leading-relaxed whitespace-pre-wrap font-sans">
                      {c.content}
                    </p>
                  </div>
                ))
              ) : (
                <div className="text-center py-6 text-stone-400 text-xs italic">
                  まだコメントはありません。最初の感想を投稿してみましょう。
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
