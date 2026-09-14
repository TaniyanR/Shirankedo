import React from 'react';
import { X, BookOpen, AlertCircle } from 'lucide-react';

interface ShirankedoAboutModalProps {
  onClose: () => void;
}

export const ShirankedoAboutModal: React.FC<ShirankedoAboutModalProps> = ({ onClose }) => {
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-stone-950/70 backdrop-blur-xs flex justify-center p-3 sm:p-6 md:py-10">
      <div className="relative bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-stone-200 overflow-hidden flex flex-col my-auto">
        <div className="sticky top-0 bg-white/95 backdrop-blur-md px-6 py-4 border-b border-stone-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <BookOpen className="w-5 h-5 text-amber-500" />
            <h2 className="text-lg font-black text-stone-900">しらんけど指数とは？</h2>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-full hover:bg-stone-100 text-stone-500 hover:text-stone-900 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 sm:p-8 space-y-5 text-stone-800 text-sm sm:text-base leading-relaxed overflow-y-auto max-h-[80vh]">
          <p>
            「しらんけど指数」は、いまインターネット上でどれくらい話題になっているのかを、独自アルゴリズムで算出した<strong>100点満点の話題度指標</strong>です。
          </p>

          <div className="bg-stone-50 border border-stone-200 rounded-2xl p-4 space-y-2">
            <h3 className="font-bold text-stone-900 text-xs tracking-wider uppercase">📊 指数の目安</h3>
            <ul className="space-y-1.5 text-xs sm:text-sm">
              <li><strong className="text-red-600">80〜100点：めっちゃ話題</strong>（検索・SNS・動画などで同時多発的に急上昇中）</li>
              <li><strong className="text-amber-600">60〜79点：かなり話題</strong>（特定のコミュニティや媒体で大きな反響）</li>
              <li><strong className="text-blue-600">30〜59点：話題</strong>（じわじわ関心が高まっている状態）</li>
              <li><strong className="text-stone-600">0〜29点：ちょい話題</strong>（一部でささやかれ始めている兆候）</li>
            </ul>
          </div>

          <h3 className="text-base font-bold text-stone-900 pt-2">指数の特徴と集計ポリシー</h3>
          <ul className="list-disc pl-5 space-y-2 text-stone-700 text-xs sm:text-sm">
            <li><strong>複数のトレンド情報を参照：</strong>Google検索需要、Yahoo!リアルタイム言及、YouTube急上昇、大手ニュース閲覧動向、ゲームストアなどを横断して重み付け集計します。</li>
            <li><strong>複合的な話題度：</strong>単一のSNSの特定アカウントだけが盛り上がっている話題よりも、「検索されていて、ニュースでも報道されている」ような多角的トピックを高く評価します。</li>
            <li><strong>時間の経過とともに変動：</strong>ネットの関心は移り変わるため、ピークを過ぎると指数は自動的に下降します。</li>
          </ul>

          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-1.5 text-xs sm:text-sm text-amber-950">
            <div className="flex items-center gap-1.5 font-bold text-amber-900">
              <AlertCircle className="w-4 h-4 text-amber-600" />
              <span>⚠️ ご利用にあたっての注意事項</span>
            </div>
            <ul className="list-disc pl-4 space-y-1 text-xs text-amber-900/90">
              <li>本指数は<strong>世論調査や統計学的な標本調査ではありません</strong>。</li>
              <li>実際の検索回数やPV数の絶対値そのものではありません。</li>
              <li>当サイト独自の推定・集計スコアであり、Google社、LINEヤフー社、YouTube等の各情報元から公認・提携されたものではありません。</li>
            </ul>
          </div>

          <div className="bg-stone-900 text-stone-100 p-4 rounded-xl text-center font-serif text-sm sm:text-base font-bold">
            「なんか今これめっちゃ見かけるな、を数字にしたようなものです。しらんけど。」
          </div>
        </div>
      </div>
    </div>
  );
};
