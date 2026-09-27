import React from 'react';
import { ExternalLink } from 'lucide-react';

const staticPages = [
  { title: 'サイトについて', slug: 'about' },
  { title: '相互リンク依頼', slug: 'trade' },
  { title: 'お知らせ', slug: 'news' },
  { title: 'プライバシーポリシー', slug: 'privacy-policy' },
  { title: 'お問い合わせ', slug: 'que' },
];

export const StaticPagesTab: React.FC = () => {
  return (
    <div className="space-y-6 animate-fade-in">
      <div className="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
        <h2 className="text-xl sm:text-2xl font-black text-stone-900 tracking-tight flex items-center gap-2">
          <span>📑</span>
          <span>個別ページ一覧</span>
        </h2>
        <p className="text-xs text-stone-500 mt-1">
          サイト内の固定ページをまとめて確認できます。
        </p>
      </div>

      <div className="bg-white rounded-3xl border border-stone-200 p-6 shadow-sm overflow-x-auto">
        <table className="w-full text-left text-xs border-collapse">
          <thead>
            <tr className="border-b border-stone-100 text-stone-400 font-bold">
              <th className="py-3">ページ名</th>
              <th className="py-3">URL</th>
              <th className="py-3 text-right">表示</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-stone-100">
            {staticPages.map((page) => {
              const url = `/page.php?slug=${encodeURIComponent(page.slug)}`;
              return (
                <tr key={page.slug} className="hover:bg-stone-50 transition-colors">
                  <td className="py-4 pr-4 font-black text-stone-900">{page.title}</td>
                  <td className="py-4 pr-4">
                    <code className="text-[11px] text-stone-500 break-all">{url}</code>
                  </td>
                  <td className="py-4 text-right">
                    <a
                      href={url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold text-[11px] transition-colors"
                    >
                      <span>ページを開く</span>
                      <ExternalLink className="w-3.5 h-3.5" />
                    </a>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
};
