import React, { useState } from 'react';
import { X, Send, CheckCircle2, ShieldCheck, Info, FileText } from 'lucide-react';

interface PageModalProps {
  slug: 'about' | 'privacy-policy' | 'que';
  onClose: () => void;
  onSwitchSlug: (newSlug: 'about' | 'privacy-policy' | 'que') => void;
}

export const PageModal: React.FC<PageModalProps> = ({ slug, onClose, onSwitchSlug }) => {
  // お問い合わせフォームの状態
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name || !email || !message) return;
    setSubmitted(true);
  };

  return (
    <div className="fixed inset-0 z-50 bg-stone-950/70 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div className="bg-white max-w-3xl w-full rounded-3xl border border-stone-200 shadow-2xl overflow-hidden my-8">
        
        {/* Navigation Tab Header */}
        <div className="bg-stone-50 border-b border-stone-200 px-6 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2 overflow-x-auto">
            <button
              onClick={() => onSwitchSlug('about')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 ${
                slug === 'about'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-600 hover:bg-stone-200'
              }`}
            >
              <Info className="w-3.5 h-3.5" />
              サイトについて
            </button>
            <button
              onClick={() => onSwitchSlug('privacy-policy')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 ${
                slug === 'privacy-policy'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-600 hover:bg-stone-200'
              }`}
            >
              <ShieldCheck className="w-3.5 h-3.5" />
              プライバシーポリシー
            </button>
            <button
              onClick={() => onSwitchSlug('que')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 ${
                slug === 'que'
                  ? 'bg-stone-900 text-white shadow-xs'
                  : 'text-stone-600 hover:bg-stone-200'
              }`}
            >
              <Send className="w-3.5 h-3.5" />
              お問い合わせ
            </button>
          </div>

          <button
            onClick={onClose}
            className="w-8 h-8 rounded-full bg-stone-200 hover:bg-stone-300 text-stone-700 flex items-center justify-center font-bold text-sm transition-colors shrink-0"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Content Body */}
        <div className="p-6 sm:p-8 max-h-[75vh] overflow-y-auto space-y-6 text-stone-700 text-xs sm:text-sm leading-relaxed">
          
          {/* ================= サイトについて ================= */}
          {slug === 'about' && (
            <div className="space-y-6">
              <div className="border-b border-stone-100 pb-4">
                <h1 className="text-2xl font-black text-stone-950 tracking-tight">サイトについて</h1>
                <p className="text-xs text-stone-500 mt-1">「しらんけど」の運営理念・基本情報・リンクポリシー</p>
              </div>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 しらんけど 紹介 】
                </h2>
                <p>
                  <strong>しらんけど</strong>は、インターネット上やSNSで今話題のトピック・ニュースを自動収集し、客観的な一次報道や公式発表の事実関係をもとに整理してお届けするトレンド情報サイトです。
                </p>
                <p>
                  噂や偏向報道、過激な誹謗中傷に流されず、「実際のところ何が起きているのか？」を分かりやすくまとめ、記事の締めくくりには関西特有のクッション言葉である<strong>「〜しらんけど。」</strong>を添えることで、過剰に白黒をつけず肩の力を抜いて情報に接していただくことを目指しています。
                </p>
              </section>

              <section className="space-y-2 bg-stone-50 p-5 rounded-2xl border border-stone-200 font-mono text-xs">
                <h2 className="text-base font-black text-stone-950 font-sans mb-1">【 当サイト情報 】</h2>
                <div>サイト名：しらんけど</div>
                <div>URL：https://shirankedo.bichi.xyz （または現在のドメイン）</div>
                <div>運営形態：トレンド自動分析・事実検証メディア</div>
                <div>免責理念：客観的事実を尊重しますが、最終判断は各自の責任でお願いします（しらんけど）。</div>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 リンクについて 】
                </h2>
                <p>
                  当サイトは<strong>完全リンクフリー</strong>です。<br />
                  トップページはもちろん、どの個別記事・カテゴリページに対しても事前の許可なく自由にリンクを貼っていただいて構いません。<br />
                  ただし、記事内で引用・紹介している各ニュース配信元・メディア企業様・権利者様の画像・動画等の著作物につきましては、権利元の規約に従う必要がありますので、二次利用や無断転載・ダウンロードはご遠慮ください。
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 相互リンクについて 】
                </h2>
                <p>
                  当サイトでは現在、個別での相互リンクの募集は原則として行っておりません。誠に恐れ入りますが、あらかじめご了承ください。
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 独自指標「しらんけど指数」について 】
                </h2>
                <p>
                  当サイトでは、各話題の「勢い」「注目度」「話題の急上昇性」を0〜100点のスコアで可視化する「しらんけど指数」を独自に算出しています。点数が高いほど世間で大きな盛り上がりを見せている話題となりますが、真偽の最終保証を行うものではありません。しらんけど。
                </p>
              </section>
            </div>
          )}

          {/* ================= プライバシーポリシー ================= */}
          {slug === 'privacy-policy' && (
            <div className="space-y-6">
              <div className="border-b border-stone-100 pb-4">
                <h1 className="text-2xl font-black text-stone-950 tracking-tight">Privacy Policy</h1>
                <p className="text-xs text-stone-500 mt-1">個人情報保護方針・アクセス解析および免責事項について</p>
              </div>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 しらんけど について 】
                </h2>
                <p>
                  「しらんけど」（以下「当サイト」）にお越しいただき誠にありがとうございます。当サイトでは、適切な広告配信およびアクセス解析技術を活用して運営を行っております。
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 リンクについて 】
                </h2>
                <p>
                  当サイトはリンクフリーです。どのページのどの記事にリンクを貼っていただいてもかまいません。ただし画像や引用メディアは元サイト・権利者様のものであり、お借りしている・引用しているだけですので、二次使用やダウンロードはご遠慮ください。
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 個人情報の利用目的 】
                </h2>
                <p>
                  当サイトでは、お問い合わせフォームのご利用時やコメント投稿時などに、氏名（ハンドルネーム）、メールアドレス等の個人情報をご入力いただく場合がございます。<br />
                  これらの個人情報は、ご質問への回答や必要な情報を電子メール等でご連絡する場合、あるいはコメント欄の健全な運営・スパム防止にのみ利用させていただくものであり、それ以外の目的では利用いたしません。
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 個人情報の第三者への開示 】
                </h2>
                <p>
                  当サイトでは、お預かりした個人情報を適切に管理し、ご本人様の承諾がある場合、法令に基づき開示が必要な場合、人の生命・身体または財産の保護のために必要な場合を除いて第三者に開示することはありません。
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 アクセス解析ツールについて 】
                </h2>
                <p>
                  当サイトでは、サイトの利用動向を分析し改善に役立てる目的で「Googleアナリティクス」等のアクセス解析ツールを使用する場合があります。トラフィックデータの収集のためにCookie（クッキー）を使用していますが、このデータは匿名で収集されており個人を特定するものではありません。
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="text-base font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                  【 広告配信に関して 】
                </h2>
                <p>
                  当サイトでは、第三者配信事業者による広告サービスや成果報酬型広告（アフィリエイトプログラム）を利用する場合があります。成果報酬型広告の効果測定および不正防止のため、閲覧したサイトのURL、表示・クリック日時等のアクセス情報が外部事業者に送信される場合がありますが、個人を特定する情報は含まれず、目的外利用されることはありません。
                </p>
              </section>

              <section className="space-y-2 bg-amber-50/80 border border-amber-200 p-5 rounded-2xl text-amber-950">
                <h2 className="text-base font-black text-amber-900 mb-1">【 免責事項 】</h2>
                <p>
                  当サイトからリンクやバナーなどによって他のサイトに移動された場合、移動先サイトで提供される情報、サービス等について当サイトは一切の責任を負いません。<br />
                  コンテンツ・情報につきましては、一次情報に基づき可能な限り正確な情報を掲載するよう努めておりますが、情報の即時性やネットトレンドの性質上、誤情報が入り込んだり情報が古くなっている場合もございます。<br />
                  当サイトに掲載された内容によって生じた損害等の一切の責任を負いかねますのでご了承ください。サイト名の通り、最終判断はご自身にてお願いいたします。しらんけど。
                </p>
              </section>
            </div>
          )}

          {/* ================= お問い合わせ ================= */}
          {slug === 'que' && (
            <div className="space-y-6">
              <div className="border-b border-stone-100 pb-4">
                <h1 className="text-2xl font-black text-stone-950 tracking-tight">お問い合わせ</h1>
                <p className="text-xs text-stone-500 mt-1">ご意見・ご要望・権利関係のご連絡は下記フォームよりお願いいたします。</p>
              </div>

              {submitted ? (
                <div className="bg-emerald-50 border-2 border-emerald-300 rounded-2xl p-6 sm:p-8 text-center space-y-3">
                  <CheckCircle2 className="w-10 h-10 text-emerald-600 mx-auto" />
                  <h2 className="text-lg font-black text-emerald-950">お問い合わせを送信しました</h2>
                  <p className="text-xs text-emerald-800 leading-relaxed max-w-md mx-auto">
                    お問い合わせいただき誠にありがとうございます。<br />
                    内容を確認の上、必要な場合はご連絡差し上げます。しらんけど。
                  </p>
                  <div className="pt-2">
                    <button
                      onClick={onClose}
                      className="px-5 py-2.5 rounded-xl bg-stone-950 hover:bg-stone-800 text-white font-bold text-xs transition-colors"
                    >
                      閉じる
                    </button>
                  </div>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                  <div className="space-y-1">
                    <label className="block text-xs font-bold text-stone-800">
                      お名前 <span className="text-rose-600">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      placeholder="山田 太郎 / ハンドルネーム可"
                      className="w-full text-xs p-3 rounded-xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="block text-xs font-bold text-stone-800">
                      メールアドレス <span className="text-rose-600">*</span>
                    </label>
                    <input
                      type="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="example@domain.com"
                      className="w-full text-xs p-3 rounded-xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="block text-xs font-bold text-stone-800">件名</label>
                    <input
                      type="text"
                      value={subject}
                      onChange={(e) => setSubject(e.target.value)}
                      placeholder="記事内容について / サイトに関するご要望 等"
                      className="w-full text-xs p-3 rounded-xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="block text-xs font-bold text-stone-800">
                      お問い合わせ内容 <span className="text-rose-600">*</span>
                    </label>
                    <textarea
                      required
                      rows={5}
                      value={message}
                      onChange={(e) => setMessage(e.target.value)}
                      placeholder="お問い合わせ内容を具体的にご入力ください。"
                      className="w-full text-xs p-3 rounded-xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 leading-relaxed"
                    />
                  </div>

                  <div className="pt-2">
                    <button
                      type="submit"
                      className="w-full py-3.5 px-6 rounded-2xl bg-stone-950 hover:bg-stone-800 text-white font-black text-xs shadow-md transition-all flex items-center justify-center gap-2"
                    >
                      <Send className="w-3.5 h-3.5" />
                      <span>お問い合わせを送信する</span>
                    </button>
                  </div>

                  <p className="text-[11px] text-stone-400 text-center font-serif pt-1">
                    「送信いただいた内容はプライバシーポリシーに則り適切に取り扱います。しらんけど。」
                  </p>
                </form>
              )}
            </div>
          )}

        </div>
      </div>
    </div>
  );
};
