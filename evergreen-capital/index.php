<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$brand   = setting('site_name', 'Evergreen Capital');
$rate    = (float)setting('loan_rate', '4.5');
$sym     = setting('currency_symbol', '$');
$loanMin = (float)setting('loan_min', '500');
$loanMax = (float)setting('loan_max', '100000');
$calcDefault = (int)max($loanMin, min($loanMax, round(($loanMin + $loanMax) / 4 / 500) * 500));
$calcStep = $loanMax - $loanMin > 200000 ? 5000 : 500;

site_head(t('brand.tagline'), t('hero.lede'), 'index.php');

$wave = '<svg class="wave" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M8.5 7.5a6 6 0 0 1 0 9M12 5a9.5 9.5 0 0 1 0 14M5 10a2.6 2.6 0 0 1 0 4"/></svg>';
?>

<div class="site">

  <!-- ═══ hero ═══ -->
  <section class="hero">
    <div class="hero-in">
      <div class="reveal">
        <span class="kicker"><?php echo icon('check'); ?> <?php echo te('v.kicker'); ?></span>
        <h1><?php echo te('hero.h1a'); ?> <span class="acc"><?php echo te('hero.h1em'); ?></span> <?php echo te('hero.h1b'); ?></h1>
        <p class="lede"><?php echo te('hero.lede'); ?></p>
        <div class="cta">
          <a class="btn btn-brass" href="<?php echo base_url('register.php'); ?>"><?php echo te('hero.cta_open'); ?>
            <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          <a class="btn-ghost-light" href="#product"><?php echo te('hero.cta_see'); ?></a>
        </div>
        <ul class="v-badges">
          <li><?php echo icon('check'); ?> <?php echo te('v.badge1'); ?></li>
          <li><?php echo icon('check'); ?> <?php echo te('v.badge2'); ?></li>
          <li><?php echo icon('check'); ?> <?php echo te('v.badge3'); ?></li>
        </ul>
      </div>

      <div class="v-stage reveal" style="--d:.12s" aria-hidden="true">
        <span class="v-ring r1"></span><span class="v-ring r2"></span>

        <div class="v-app">
          <div class="v-app-top">
            <span class="who"><i>AL</i><span><?php echo te('v.app_hello'); ?><b><?php echo te('demo.person'); ?></b></span></span>
            <span class="bell"><?php echo icon('mail'); ?></span>
          </div>
          <span class="lbl"><?php echo te('v.app_total'); ?></span>
          <span class="fig"><?php echo money(48250.90); ?></span>
          <span class="delta">▲ 12.4% <?php echo te('v.app_month'); ?></span>
          <svg class="v-spark" viewBox="0 0 300 64" preserveAspectRatio="none">
            <defs><linearGradient id="vsg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3EE0B0" stop-opacity=".45"/><stop offset="1" stop-color="#3EE0B0" stop-opacity="0"/></linearGradient></defs>
            <path d="M0 50 C25 46 35 30 60 34 S95 52 120 40 S160 14 185 22 S225 40 250 24 S285 8 300 6 V64 H0Z" fill="url(#vsg)"/>
            <path d="M0 50 C25 46 35 30 60 34 S95 52 120 40 S160 14 185 22 S225 40 250 24 S285 8 300 6" fill="none" stroke="#3EE0B0" stroke-width="2.4" stroke-linecap="round"/>
            <circle cx="300" cy="6" r="4" fill="#fff" stroke="#3EE0B0" stroke-width="2.4"/>
          </svg>
          <div class="v-qa">
            <span><i><?php echo icon('send'); ?></i><?php echo te('v.qa_send'); ?></span>
            <span><i><?php echo icon('plus'); ?></i><?php echo te('v.qa_add'); ?></span>
            <span><i><?php echo icon('card'); ?></i><?php echo te('v.qa_cards'); ?></span>
            <span><i><?php echo icon('bank'); ?></i><?php echo te('v.qa_more'); ?></span>
          </div>
          <div class="v-tx"><i><?php echo icon('down'); ?></i><span><b><?php echo te('v.tx_salary'); ?></b><small><?php echo te('demo.today'); ?></small></span><em class="in">+<?php echo money(5200); ?></em></div>
          <div class="v-tx"><i><?php echo icon('card'); ?></i><span><b><?php echo te('v.tx_grocer'); ?></b><small><?php echo te('demo.yesterday'); ?></small></span><em>−<?php echo money(86.40); ?></em></div>
        </div>

        <div class="v-cardx">
          <div class="row"><span class="nm"><?php echo brand_mark(22); ?> Evergreen</span><span class="tier">Platinum</span></div>
          <div class="row"><span class="chip"></span><?php echo $wave; ?></div>
          <span class="num">4716 •••• •••• 2048</span>
          <div class="row">
            <span><small>Card holder</small><b>AMARA LEWIS</b></span>
            <span><small>Expires</small><b>09/30</b></span>
            <span class="circles"><i></i><i></i></span>
          </div>
        </div>

        <div class="v-chip c1"><span class="ico"><?php echo icon('check'); ?></span><span><b><?php echo te('v.chip1_h'); ?></b><small>+<?php echo money(1240); ?> · TRF8A19C</small></span></div>
        <div class="v-chip c2"><span class="v-ringp" style="--p:72" data-v="72%"></span><span><b><?php echo te('v.chip2_h'); ?></b><small><?php echo te('v.chip2_s'); ?></small></span></div>
      </div>
    </div>
  </section>

  <!-- ═══ stats ═══ -->
  <div class="v-stats reveal">
    <div><b><span class="count" data-count="<?php echo e($rate); ?>" data-dp="1" data-suffix="%"><?php echo e($rate); ?>%</span></b><small><?php echo te('v.stat1'); ?></small></div>
    <div><b><?php echo e($sym); ?>0</b><small><?php echo te('v.stat2'); ?></small></div>
    <div><b>60<i class="u">s</i></b><small><?php echo te('v.stat3'); ?></small></div>
    <div><b><span class="count" data-count="190" data-dp="0" data-suffix="+">190+</span></b><small><?php echo te('v.stat4'); ?></small></div>
  </div>

  <!-- ═══ features ═══ -->
  <section class="sec" id="accounts">
    <div class="sec-head center reveal">
      <div class="numlabel"><b>01</b> <?php echo te('feat.tag'); ?></div>
      <h2><?php echo te('feat.title'); ?></h2>
      <p><?php echo te('feat.sub'); ?></p>
    </div>

    <div class="bento">
      <article class="tall dark reveal">
        <div>
          <?php echo icon('send'); ?>
          <h3><?php echo te('feat.move_h'); ?></h3>
          <p><?php echo te('feat.move_p'); ?></p>
        </div>
        <div><span class="big">3</span><p><?php echo te('feat.type_count'); ?></p></div>
      </article>
      <article class="wide reveal" style="--d:.06s">
        <?php echo icon('bank'); ?>
        <h3><?php echo te('feat.borrow_h'); ?></h3>
        <p><?php echo te('feat.borrow_p'); ?></p>
      </article>
      <article class="reveal" style="--d:.1s">
        <?php echo icon('card'); ?>
        <h3><?php echo te('feat.cards_h'); ?></h3>
        <p><?php echo te('feat.cards_p'); ?></p>
      </article>
      <article class="reveal" style="--d:.14s">
        <?php echo icon('doc'); ?>
        <h3><?php echo te('feat.stmt_h'); ?></h3>
        <p><?php echo te('feat.stmt_p'); ?></p>
      </article>
      <article class="reveal" style="--d:.18s">
        <?php echo icon('users'); ?>
        <h3><?php echo te('feat.benef_h'); ?></h3>
        <p><?php echo te('feat.benef_p'); ?></p>
      </article>
      <article class="wide reveal" style="--d:.06s">
        <?php echo icon('check'); ?>
        <h3><?php echo te('feat.receipt_h'); ?></h3>
        <p><?php echo te('feat.receipt_p'); ?></p>
      </article>
      <article class="wide reveal" style="--d:.1s">
        <?php echo icon('mail'); ?>
        <h3><?php echo te('feat.notice_h'); ?></h3>
        <p><?php echo te('feat.notice_p'); ?></p>
      </article>
    </div>
  </section>

  <!-- ═══ showcase ═══ -->
  <section class="sec" id="product" style="padding-top:20px">
    <div class="sec-head reveal">
      <div class="numlabel"><b>02</b> <?php echo te('show.label'); ?></div>
      <h2><?php echo te('show.title'); ?></h2>
      <p><?php echo te('show.sub'); ?></p>
    </div>

    <div class="showcase reveal" data-tabs style="--d:.08s">
      <div class="tabs-pill" role="tablist" aria-label="<?php echo te('show.label'); ?>">
        <button role="tab" aria-selected="true" aria-controls="tab-overview" id="t1">
          <strong><?php echo te('show.tab1_h'); ?></strong><span><?php echo te('show.tab1_s'); ?></span></button>
        <button role="tab" aria-selected="false" aria-controls="tab-send" id="t2">
          <strong><?php echo te('show.tab2_h'); ?></strong><span><?php echo te('show.tab2_s'); ?></span></button>
        <button role="tab" aria-selected="false" aria-controls="tab-loan" id="t3">
          <strong><?php echo te('show.tab3_h'); ?></strong><span><?php echo te('show.tab3_s'); ?></span></button>
      </div>

      <div class="showcase-frame">
        <div class="mockbar" aria-hidden="true"><i></i><i></i><i></i></div>

        <div class="showcase-screen" role="tabpanel" id="tab-overview" aria-labelledby="t1">
          <div class="bal" style="padding:22px;margin-bottom:14px">
            <div class="bal-in">
              <span class="lbl"><?php echo te('show.available'); ?></span>
              <span class="fig" style="font-size:36px"><?php echo money(28450); ?></span>
              <div class="meta">
                <div><span><?php echo te('show.account'); ?></span><b>3049 118 227</b></div>
                <div><span><?php echo te('show.held'); ?></span><b><?php echo money(240); ?></b></div>
                <div><span><?php echo te('show.type'); ?></span><b><?php echo te('show.savings'); ?></b></div>
              </div>
            </div>
          </div>
          <div class="mockrow"><span class="mi"><?php echo icon('down'); ?></span>
            <span><b><?php echo te('show.salary'); ?></b><small><?php echo te('demo.today'); ?></small></span>
            <span class="amt amt-credit">+<?php echo money(5200); ?></span></div>
          <div class="mockrow"><span class="mi"><?php echo icon('card'); ?></span>
            <span><b><?php echo te('show.card_buy'); ?></b><small><?php echo te('demo.yesterday'); ?></small></span>
            <span class="amt amt-debit">−<?php echo money(86.40); ?></span></div>
          <div class="mockrow"><span class="mi"><?php echo icon('send'); ?></span>
            <span><b><?php echo te('show.transfer_out'); ?></b><small><?php echo te('show.pending'); ?></small></span>
            <span class="amt amt-debit">−<?php echo money(1200); ?></span></div>
        </div>

        <div class="showcase-screen" role="tabpanel" id="tab-send" aria-labelledby="t2" hidden>
          <div class="numlabel" style="margin-bottom:14px"><b><?php echo te('show.step2'); ?></b> <?php echo te('show.pin'); ?></div>
          <p style="font-size:14px;color:var(--muted);margin-bottom:16px"><?php echo te('show.pin_note'); ?></p>
          <div class="field pinbox"><label class="lbl-t"><?php echo te('show.pin'); ?></label>
            <input type="password" value="0000" readonly tabindex="-1" style="pointer-events:none"></div>
          <div class="divider"></div>
          <dl class="kv">
            <dt><?php echo te('show.to'); ?></dt><dd>J. Mensah</dd>
            <dt><?php echo te('show.account'); ?></dt><dd>3072 004 918</dd>
            <dt><?php echo te('show.fee'); ?></dt><dd><?php echo money(6); ?></dd>
            <dt><?php echo te('show.total'); ?></dt><dd><strong><?php echo money(1206); ?></strong></dd>
          </dl>
        </div>

        <div class="showcase-screen" role="tabpanel" id="tab-loan" aria-labelledby="t3" hidden>
          <?php $demo = loan_quote(25000, 24, $rate); ?>
          <div class="bal" style="padding:22px;margin-bottom:14px">
            <div class="bal-in">
              <span class="lbl"><?php echo te('show.monthly'); ?></span>
              <span class="fig" style="font-size:36px"><?php echo money($demo['monthly']); ?></span>
              <div class="meta">
                <div><span><?php echo te('show.borrowing'); ?></span><b><?php echo money(25000); ?></b></div>
                <div><span><?php echo te('show.over'); ?></span><b>24 <?php echo te('show.months_short'); ?></b></div>
                <div><span><?php echo te('show.total'); ?></span><b><?php echo money($demo['total']); ?></b></div>
              </div>
            </div>
          </div>
          <div class="mockrow"><span class="mi"><?php echo icon('check'); ?></span>
            <span><b><?php echo te('show.rate_fixed'); ?></b><small><?php echo te('show.rate_note'); ?></small></span></div>
          <div class="mockrow"><span class="mi"><?php echo icon('check'); ?></span>
            <span><b><?php echo te('show.reviewed'); ?></b><small><?php echo te('show.reviewed_note'); ?></small></span></div>
          <div class="mockrow"><span class="mi"><?php echo icon('check'); ?></span>
            <span><b><?php echo te('show.early'); ?></b><small><?php echo te('show.early_note'); ?></small></span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══ cards ═══ -->
  <section class="sec" id="cards">
    <div class="split">
      <div class="v-cards reveal" aria-hidden="true">
        <div class="v-cardx k1">
          <div class="row"><span class="nm"><?php echo brand_mark(22); ?> Evergreen</span><span class="tier">Black</span></div>
          <div class="row"><span class="chip"></span><?php echo $wave; ?></div>
          <span class="num">5412 •••• •••• 7781</span>
          <div class="row"><span><small>Card holder</small><b>J. MENSAH</b></span><span class="circles"><i></i><i></i></span></div>
        </div>
        <div class="v-cardx k2">
          <div class="row"><span class="nm"><?php echo brand_mark(22); ?> Evergreen</span><span class="tier">Platinum</span></div>
          <div class="row"><span class="chip"></span><?php echo $wave; ?></div>
          <span class="num">4716 •••• •••• 2048</span>
          <div class="row"><span><small>Card holder</small><b>AMARA LEWIS</b></span><span><small>Expires</small><b>09/30</b></span><span class="circles"><i></i><i></i></span></div>
        </div>
      </div>
      <div class="reveal" style="--d:.08s">
        <div class="numlabel"><b>03</b> <?php echo te('v.cards_label'); ?></div>
        <h2><?php echo te('split.card_h'); ?></h2>
        <p><?php echo te('split.card_p'); ?></p>
        <div class="v-perks">
          <div><?php echo icon('card'); ?><span><b><?php echo te('v.perk1_h'); ?></b><small><?php echo te('v.perk1_s'); ?></small></span></div>
          <div><?php echo icon('lock'); ?><span><b><?php echo te('v.perk2_h'); ?></b><small><?php echo te('v.perk2_s'); ?></small></span></div>
          <div><?php echo icon('eye'); ?><span><b><?php echo te('v.perk3_h'); ?></b><small><?php echo te('v.perk3_s'); ?></small></span></div>
          <div><?php echo icon('send'); ?><span><b><?php echo te('v.perk4_h'); ?></b><small><?php echo te('v.perk4_s'); ?></small></span></div>
        </div>
        <a class="btn btn-primary" href="<?php echo base_url('register.php'); ?>"><?php echo te('split.card_cta'); ?></a>
      </div>
    </div>
  </section>

  <!-- ═══ calculator ═══ -->
  <section class="sec" id="lending">
    <div class="sec-head center reveal">
      <div class="numlabel"><b>04</b> <?php echo te('calc.label'); ?></div>
      <h2><?php echo te('calc.title'); ?></h2>
      <p><?php echo te('calc.sub'); ?></p>
    </div>

    <div class="calcwrap reveal" style="--d:.08s">
      <div class="fields">
        <form id="loanCalc" data-rate="<?php echo e($rate); ?>" data-symbol="<?php echo e($sym); ?>" onsubmit="return false">
          <div class="field">
            <label for="amount"><?php echo te('calc.amount'); ?></label>
            <div class="money-in"><span><?php echo e($sym); ?></span>
              <input type="text" id="amount" name="amount" inputmode="decimal" value="<?php echo $calcDefault; ?>"></div>
            <input class="range" type="range" min="<?php echo (int)$loanMin; ?>"
                   max="<?php echo (int)$loanMax; ?>" step="<?php echo $calcStep; ?>" value="<?php echo $calcDefault; ?>"
                   data-mirror="amount" aria-label="<?php echo te('calc.amount'); ?>" style="margin-top:16px">
            <span class="hint"><?php echo te('calc.from'); ?> <?php echo money($loanMin); ?> <?php echo te('calc.to'); ?> <?php echo money($loanMax); ?></span>
          </div>

          <div class="field">
            <label for="tenure"><?php echo te('calc.tenure'); ?></label>
            <select id="tenure" name="tenure_months">
              <?php foreach (array_filter(array_map('trim', explode(',', setting('loan_tenures', '3,6,12,24,36')))) as $tn) {
                echo '<option value="' . (int)$tn . '"' . ((int)$tn === 24 ? ' selected' : '') . '>' . (int)$tn . ' ' . te('show.months_short') . '</option>';
              } ?>
            </select>
          </div>

          <p class="small muted mb0"><?php echo te('calc.note'); ?></p>
        </form>
      </div>

      <div class="result">
        <span class="lbl"><?php echo te('calc.repay'); ?></span>
        <span class="fig" id="qMonthly">—</span>
        <dl>
          <dt><?php echo te('calc.total'); ?></dt><dd id="qTotal">—</dd>
          <dt><?php echo te('calc.interest'); ?></dt><dd id="qInterest">—</dd>
          <dt><?php echo te('calc.rate'); ?></dt><dd><?php echo e($rate); ?>% <?php echo te('calc.peryear'); ?></dd>
        </dl>
        <a class="btn btn-brass btn-block" href="<?php echo base_url('register.php'); ?>"><?php echo te('calc.apply'); ?></a>
      </div>
    </div>
  </section>

  <!-- ═══ security ═══ -->
  <section class="sec">
    <div class="band reveal">
      <div class="numlabel"><b>05</b> <?php echo te('sec.label'); ?></div>
      <div style="max-width:58ch;margin-bottom:36px">
        <h2><?php echo te('sec.title'); ?></h2>
        <p><?php echo te('sec.body'); ?></p>
      </div>
      <div class="fgrid">
        <article><?php echo icon('lock'); ?><h3><?php echo te('sec.layer_h'); ?></h3>
          <p><?php echo te('sec.layer_p'); ?></p></article>
        <article><?php echo icon('eye'); ?><h3><?php echo te('sec.seen_h'); ?></h3>
          <p><?php echo te('sec.seen_p'); ?></p></article>
        <article><?php echo icon('id'); ?><h3><?php echo te('sec.id_h'); ?></h3>
          <p><?php echo te('sec.id_p'); ?></p></article>
      </div>
    </div>
  </section>

  <!-- ═══ steps ═══ -->
  <section class="sec" id="transfers">
    <div class="sec-head center reveal">
      <div class="numlabel"><b>06</b> <?php echo te('steps.label'); ?></div>
      <h2><?php echo te('steps.title'); ?></h2>
      <p><?php echo te('steps.sub'); ?></p>
    </div>
    <div class="steps">
      <article class="reveal"><h3><?php echo te('steps.s1_h'); ?></h3>
        <p><?php echo te('steps.s1_p'); ?></p></article>
      <article class="reveal" style="--d:.08s"><h3><?php echo te('steps.s2_h'); ?></h3>
        <p><?php echo te('steps.s2_p'); ?></p></article>
      <article class="reveal" style="--d:.16s"><h3><?php echo te('steps.s3_h'); ?></h3>
        <p><?php echo te('steps.s3_p'); ?></p></article>
    </div>
  </section>

  <!-- ═══ people ═══ -->
  <section class="sec">
    <div class="split flip">
      <div class="split-art reveal">
        <img src="<?php echo base_url('assets/img/community.jpg'); ?>" alt="" width="1200" height="800" loading="lazy">
      </div>
      <div class="reveal" style="--d:.08s">
        <div class="numlabel"><b>07</b> <?php echo te('ppl.label'); ?></div>
        <h2><?php echo te('ppl.title'); ?></h2>
        <p><?php echo te('ppl.body'); ?></p>
        <ul class="ticks">
          <li><?php echo icon('check'); ?><span><?php echo te('ppl.t1'); ?></span></li>
          <li><?php echo icon('check'); ?><span><?php echo te('ppl.t2'); ?></span></li>
          <li><?php echo icon('check'); ?><span><?php echo te('ppl.t3'); ?></span></li>
          <li><?php echo icon('check'); ?><span><?php echo te('ppl.t4'); ?></span></li>
        </ul>
        <a class="btn btn-primary" href="<?php echo base_url('register.php'); ?>"><?php echo te('hero.cta_open'); ?></a>
      </div>
    </div>
  </section>

  <!-- ═══ faq ═══ -->
  <section class="sec">
    <div class="v-faq">
      <div class="reveal">
        <div class="numlabel"><b>08</b> <?php echo te('v.faq_label'); ?></div>
        <div class="sec-head" style="margin-bottom:0">
          <h2><?php echo te('v.faq_title'); ?></h2>
          <p><?php echo te('v.faq_sub'); ?></p>
        </div>
        <div class="help">
          <b><?php echo te('v.faq_help_h'); ?></b>
          <p><?php echo te('v.faq_help_p'); ?></p>
          <a class="btn btn-sm" href="<?php echo base_url('help.php'); ?>"><?php echo te('v.faq_help_cta'); ?></a>
        </div>
      </div>
      <div class="faq reveal" style="--d:.08s">
        <?php for ($i = 1; $i <= 5; $i++) { ?>
          <details<?php echo $i === 1 ? ' open' : ''; ?>>
            <summary><?php echo te('v.q' . $i); ?></summary>
            <p><?php echo te('v.a' . $i); ?></p>
          </details>
        <?php } ?>
      </div>
    </div>
  </section>

  <!-- ═══ cta ═══ -->
  <section class="sec" style="padding-bottom:0">
    <div class="cta-band reveal">
      <h2><?php echo te('cta.title'); ?></h2>
      <p><?php echo te('cta.body'); ?></p>
      <div class="cta-actions">
        <a class="btn btn-brass" href="<?php echo base_url('register.php'); ?>"><?php echo te('cta.open'); ?></a>
        <a class="btn" href="<?php echo base_url('contact.php'); ?>"><?php echo te('cta.talk'); ?></a>
      </div>
    </div>
  </section>
</div>

<?php site_foot();
