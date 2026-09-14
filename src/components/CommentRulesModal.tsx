import React from 'react';
import { X, ShieldAlert, AlertOctagon, CheckCircle } from 'lucide-react';

interface CommentRulesModalProps {
  onClose: () => void;
}

export const CommentRulesModal: React.FC<CommentRulesModalProps> = ({ onClose }) => {
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-stone-950/70 backdrop-blur-xs flex justify-center p-3 sm:p-6 md:py-10">
      <div className="relative bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-stone-200 overflow-hidden flex flex-col my-auto">
        <div className="sticky top-0 bg-white/95 backdrop-blur-md px-6 py-4 border-b border-stone-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <ShieldAlert className="w-5 h-5 text-rose-600" />
            <h2 className="text-lg font-black text-stone-900">誹謗中傷禁止・コメント利用ルール</h2>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-full hover:bg-stone-100 text-stone-500 hover:text-stone-900 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 sm:p-8 space-y-5 text-stone-800 text-xs sm:text-sm leading-relaxed overflow-y-auto max-h-[80vh]">
          <p className="text-stone-700">
            当サイト「しらんけど」では、ユーザーの皆様が安心して感想を共有できるクリーンな環境を守るため、以下のコメント利用ルールを厳格に定めています。
          </p>

          <div className="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl space-y-1">
            <div className="font-bold text-rose-950 text-xs">【投稿の大原則】</div>
            <p className="text-rose-900">
              投稿できる内容は<strong>文字（プレーンテキスト）のみ</strong>です。<br />
              <strong>URL（外部リンク）、HTMLタグ、画像・動画の貼り付けはすべてシステム側で完全拒絶・自動遮断</strong>されます。
            </p>
          </div>

          <h3 className="font-bold text-stone-900 text-sm pt-2 flex items-center gap-1.5">
            <AlertOctagon className="w-4 h-4 text-rose-600" />
            固く禁止される投稿（禁止事項）
          </h3>

          <ul className="list-disc pl-5 space-y-1.5 text-stone-700">
            <li><strong>個人への誹謗中傷・名誉毀損・侮辱行為</strong></li>
            <li><strong>企業や団体への悪質な中傷・風評被害の流布</strong></li>
            <li><strong>人種・国籍・信条・性別等に基づく差別的表現</strong></li>
            <li><strong>生命・身体・自由等に対する脅迫や危害の予告</strong></li>
            <li><strong>根拠のない噂・虚偽情報（デマ）・憶測の事実化</strong></li>
            <li><strong>第三者へのなりすまし行為</strong></li>
            <li><strong>一般人や関係者の個人情報（本名・住所・電話番号・勤務先・通学先）の晒し行為</strong></li>
            <li><strong>過度な煽り・挑発・他者への攻撃的な言動</strong></li>
            <li><strong>犯罪予告・違法行為の教唆・幇助</strong></li>
            <li><strong>荒らし行為・同一内容の連続投稿（連投スパム）</strong></li>
            <li><strong>他サイトや特定サービスへの勧誘・宣伝</strong></li>
          </ul>

          <h3 className="font-bold text-stone-900 text-sm pt-2 flex items-center gap-1.5">
            <CheckCircle className="w-4 h-4 text-stone-800" />
            違反投稿への対応措置
          </h3>

          <p className="text-stone-700">
            禁止事項に該当する、または運営が不適切と判断した投稿に対しては、事前の通告なく以下の措置を実施します。
          </p>

          <ul className="list-disc pl-5 space-y-1.5 text-stone-700">
            <li><strong>対象コメントの即時非表示および完全削除</strong></li>
            <li><strong>IPアドレス・端末ハッシュ識別による以後の投稿禁止（アクセス制限）</strong></li>
            <li><strong>悪質な脅迫・犯罪予告・重大な名誉毀損については、捜査機関へのログ開示および法的措置</strong></li>
          </ul>

          <div className="pt-4 border-t border-stone-200 text-[11px] text-stone-500">
            制定日: 2026年<br />
            トレンドサイトシステム「しらんけど」運営事務局
          </div>
        </div>
      </div>
    </div>
  );
};
