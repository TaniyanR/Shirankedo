import React, { useState } from 'react';
import { Image as ImageIcon, Plus, Trash2, Tag, Layers, RefreshCw } from 'lucide-react';
import { ImageItem, ImageGroup } from '../../types';

interface ImagesTabProps {
  images: ImageItem[];
  groups: ImageGroup[];
  onAddImage: (imageData: { url: string; altText: string; groupId?: string; keywords: string }) => Promise<void>;
  onDeleteImage?: (id: number) => void;
  onRefresh: () => void;
}

export const ImagesTab: React.FC<ImagesTabProps> = ({
  images,
  groups,
  onAddImage,
  onDeleteImage,
  onRefresh,
}) => {
  const [url, setUrl] = useState('');
  const [altText, setAltText] = useState('');
  const [groupId, setGroupId] = useState('');
  const [keywords, setKeywords] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [selectedGenre, setSelectedGenre] = useState('all');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!url.trim()) return;
    setIsSubmitting(true);
    try {
      await onAddImage({ url, altText, groupId: groupId || undefined, keywords });
      setUrl('');
      setAltText('');
      setKeywords('');
    } catch (err: any) {
      alert('画像登録に失敗しました: ' + err.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const filteredImages = images.filter((img) => {
    if (selectedGenre === 'all') return true;
    return img.groupId === selectedGenre;
  });

  return (
    <div className="space-y-6 animate-fade-in">
      {/* ページヘッダー (サイドバー項目名「画像・素材管理」と完全一致) */}
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-black text-stone-900 tracking-tight">画像・素材管理</h2>
            <span className="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 text-[11px] font-bold">
              {images.length} 枚 登録済
            </span>
          </div>
          <p className="text-xs text-stone-500 mt-1">
            記事サムネイルとしてAIが自動適用する画像の追加・削除およびキーワード紐付けを一元管理します。
          </p>
        </div>
        <button
          type="button"
          onClick={onRefresh}
          className="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer"
        >
          <RefreshCw className="w-3.5 h-3.5 text-stone-500" />
          <span>最新化</span>
        </button>
      </div>

      {/* 新規画像URLの登録フォーム */}
      <form onSubmit={handleSubmit} className="bg-white rounded-3xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-4">
        <h3 className="text-sm sm:text-base font-black text-stone-900 flex items-center gap-2">
          <Plus className="w-4 h-4 text-purple-600" />
          <span>新しいアイキャッチ画像URLの登録</span>
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-stone-700">画像URL *</label>
            <input
              type="url"
              required
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="https://images.unsplash.com/..."
              className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-stone-700">代替テキスト (alt)</label>
            <input
              type="text"
              value={altText}
              onChange={(e) => setAltText(e.target.value)}
              placeholder="画像の簡単な説明（例: 話題のスタジオ風景）"
              className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-stone-700">関連キーワード (カンマ区切り)</label>
            <input
              type="text"
              value={keywords}
              onChange={(e) => setKeywords(e.target.value)}
              placeholder="芸能, お笑い, テレビ, 配信, ニュース"
              className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-stone-700">カテゴリ分類 / グループ</label>
            <select
              value={groupId}
              onChange={(e) => setGroupId(e.target.value)}
              className="w-full px-3.5 py-2.5 rounded-xl border border-stone-300 text-xs bg-white"
            >
              <option value="">自動判別 (全ジャンル共通)</option>
              {groups.map((g) => (
                <option key={g.id} value={g.id}>
                  {g.name}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="flex justify-end pt-2">
          <button
            type="submit"
            disabled={isSubmitting || !url.trim()}
            className="px-5 py-2.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs transition-colors cursor-pointer disabled:opacity-50"
          >
            {isSubmitting ? '登録中...' : 'ライブラリに追加する'}
          </button>
        </div>
      </form>

      {/* 登録画像ギャラリー一覧 */}
      <div className="bg-white rounded-3xl p-6 border border-stone-200 shadow-sm space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 pb-3">
          <h3 className="text-sm sm:text-base font-black text-stone-900 flex items-center gap-2">
            <ImageIcon className="w-4 h-4 text-purple-600" />
            <span>登録済アイキャッチ画像一覧 ({filteredImages.length} 枚)</span>
          </h3>

          <div className="flex items-center gap-1 overflow-x-auto text-xs font-medium">
            <button
              type="button"
              onClick={() => setSelectedGenre('all')}
              className={`px-3 py-1 rounded-lg transition-colors cursor-pointer ${
                selectedGenre === 'all' ? 'bg-purple-100 text-purple-900 font-bold' : 'text-stone-600 hover:bg-stone-100'
              }`}
            >
              すべて ({images.length})
            </button>
            {groups.map((g) => (
              <button
                key={g.id}
                type="button"
                onClick={() => setSelectedGenre(g.id)}
                className={`px-3 py-1 rounded-lg transition-colors cursor-pointer ${
                  selectedGenre === g.id ? 'bg-purple-100 text-purple-900 font-bold' : 'text-stone-600 hover:bg-stone-100'
                }`}
              >
                {g.name}
              </button>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
          {filteredImages.map((img) => (
            <div
              key={img.id}
              className="bg-stone-50 rounded-2xl overflow-hidden border border-stone-200 group flex flex-col justify-between hover:border-purple-300 transition-all shadow-2xs"
            >
              <div className="relative aspect-video bg-stone-200 overflow-hidden">
                <img
                  src={img.url}
                  alt={img.altText}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                />
              </div>
              <div className="p-2.5 space-y-1 text-[11px] flex-1 flex flex-col justify-between">
                <div className="space-y-0.5">
                  <div className="font-bold text-stone-800 line-clamp-1">{img.altText || '素材画像'}</div>
                  <div className="text-[10px] text-stone-500 line-clamp-1">
                    {img.keywords?.join(', ') || '全般'}
                  </div>
                </div>
                {onDeleteImage && (
                  <div className="pt-2 flex justify-end">
                    <button
                      type="button"
                      onClick={() => onDeleteImage(img.id)}
                      className="p-1 rounded text-stone-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                      title="削除"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
