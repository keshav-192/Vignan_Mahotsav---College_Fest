<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.html');
  exit;
}

$uid = $_SESSION['user_id'];
$name = '';
$mhid = '';

$res = $conn->query("SELECT mhid, first_name, last_name FROM users WHERE id='" . $conn->real_escape_string($uid) . "'");
if ($row = $res->fetch_assoc()) {
  $name = trim($row['first_name'] . ' ' . $row['last_name']);
  $mhid = $row['mhid'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback - Vignan Mahotsav</title>
  <link rel="stylesheet" href="../assets/css/index.css">

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

 </head>
 <body style="background: transparent; margin: 0; padding: 0.8rem 1rem;">
  <section class="feedback" id="feedback" style="padding:0; margin:0;">
    <div class="feedback-container animated-section" style="padding:0.25rem 0 0.5rem;">
      <h2 style="margin:0 0 0.75rem 0;">Share Your Experience</h2>
      <form class="feedback-form" action="../feedback/submit_feedback.php" method="POST">
        <div class="form-row" style="display:flex;gap:1rem;flex-wrap:wrap;">
          <div class="form-group" style="flex:1 1 0;min-width:180px;">
            <label for="fb_name">Your Name</label>
            <input type="text" id="fb_name" name="name" placeholder="Enter your name" required pattern="[A-Za-z ]+" title="Use letters and spaces only" value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>" readonly style="cursor:not-allowed;">
            <small id="fb_name_hint" style="display:block;margin-top:6px;color:#ff6557;"></small>
          </div>
          <div class="form-group" style="flex:1 1 0;min-width:180px;">
            <label for="fb_mhid">Your Mahotsav Id</label>
            <input type="text" id="fb_mhid" name="mhid" placeholder="Enter your mahotsav id" required value="<?php echo htmlspecialchars($mhid, ENT_QUOTES); ?>" readonly style="cursor:not-allowed;">
            <small id="fb_mhid_hint" style="color:#ff6557;display:block;margin-top:6px;"></small>
          </div>
        </div>
        <div class="form-group" style="margin-top:0.5rem;">
          <label for="fb_rating">Rating (1-5)</label>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.4rem;width:100%;box-sizing:border-box;">
            <input type="range" id="fb_rating" name="rating" min="1" max="5" value="3" required style="width:calc(100% - 12px);max-width:100%;margin:0 6px;accent-color:#a34bff;">
            <span id="fb_rating_value" style="min-width:60px;font-size:0.9rem;color:#eaf3fc;">3 / 5</span>
          </div>
          <small id="fb_rating_hint" style="display:block;margin-top:4px;color:#ff6557;"></small>
        </div>
        <div class="form-group" style="margin-top:0.5rem;">
          <label for="fb_text">Write your feedback...</label>
          <textarea id="fb_text" name="feedback" placeholder="Share your thoughts about Vignan Mahotsav..." required style="min-height:70px;max-height:130px;"></textarea>
          <small id="fb_text_hint" style="display:block;margin-top:4px;color:#ff6557;"></small>
        </div>
        <button class="btn-submit" type="submit" style="margin-top:0.6rem;">Submit Feedback</button>
        <small id="fb_form_hint" style="display:block;margin-top:6px;font-weight:700;color:#52ffa8;"></small>
      </form>
    </div>
  </section>

  <script>
  (function(){
    const okColor = '#52ffa8';
    const errColor = '#ff6557';
    function setHint(el, input, ok, msg){
      if(!el || !input) return;
      if(ok === null){ el.textContent=''; return; }
      if(ok){ el.textContent='Valid'; el.style.color=okColor; }
      else { el.textContent = msg || ''; el.style.color=errColor; }
    }

    const nameInput = document.getElementById('fb_name');
    const nameHint = document.getElementById('fb_name_hint');
    let nameActivated = false;
    function validateName(showRequired=false){
      if(!nameInput) return true;
      const v = (nameInput.value||'').trim();
      if(!v){ setHint(nameHint, nameInput, showRequired?false:null, showRequired?'Required':''); return false; }
      const ok = /^[A-Za-z ]+$/.test(v);
      setHint(nameHint, nameInput, ok, ok?'':'Use only letters and spaces.');
      return ok;
    }
    if(nameInput){
      nameInput.addEventListener('blur', ()=>{ if(nameInput.value.trim()){ nameActivated=true; validateName(true); } else { setHint(nameHint, nameInput, null); } });
      nameInput.addEventListener('input', ()=>{ if(nameActivated){ if(nameInput.value.trim()) validateName(true); else setHint(nameHint, nameInput, null); } });
    }

    const mhidInput = document.getElementById('fb_mhid');
    const mhidHint = document.getElementById('fb_mhid_hint');
    let mhidActivated = false;
    function checkMhid(showRequired=false){
      if(!mhidInput) return true;
      const v = (mhidInput.value||'').trim();
      if(!v){ setHint(mhidHint, mhidInput, showRequired?false:null, showRequired?'Required':''); return false; }
      fetch('../feedback/verify_mhid.php?mhid=' + encodeURIComponent(v))
        .then(r => r.ok ? r.json() : null)
        .then(data => {
          if(!data){ setHint(mhidHint, mhidInput, false, 'Unable to verify MHID.'); return; }
          setHint(mhidHint, mhidInput, !!data.exists, data.exists ? '' : 'MHID not found.');
        })
        .catch(()=> setHint(mhidHint, mhidInput, false, 'Unable to verify MHID.'));
      return true;
    }
    if(mhidInput){
      mhidInput.addEventListener('blur', ()=>{ if(mhidInput.value.trim()){ mhidActivated=true; checkMhid(true); } else { setHint(mhidHint, mhidInput, null); } });
      mhidInput.addEventListener('input', ()=>{ if(mhidActivated){ if(mhidInput.value.trim()) { setHint(mhidHint, mhidInput, null); } else setHint(mhidHint, mhidInput, null); } });
    }

    const form = document.querySelector('.feedback-form');
    const textInput = document.getElementById('fb_text');
    const textHint = document.getElementById('fb_text_hint');
    const formHint = document.getElementById('fb_form_hint');
    if(form){
      form.addEventListener('submit', async (e)=>{
        e.preventDefault();
        if(formHint){ formHint.textContent=''; }
        if(nameHint){ nameHint.textContent=''; }
        if(mhidHint){ mhidHint.textContent=''; }
        if(textHint){ textHint.textContent=''; }

        let ok = true;
        if(!validateName(true)) ok = false;
        if(!mhidInput.value.trim()){ setHint(mhidHint, mhidInput, false, 'Required'); ok = false; }
        if(!textInput.value.trim()){ setHint(textHint, textInput, false, 'Required'); ok = false; }
        if(!ok) return;

        const fd = new FormData(form);
        fd.append('ajax','1');
        try{
          const res = await fetch('../feedback/submit_feedback.php', { method:'POST', body: fd });
          const data = await res.json();
          if(!data || data.ok !== true){
            const field = data && data.field ? data.field : 'form';
            const msg = data && data.msg ? data.msg : 'Submission failed.';
            if(field === 'name'){ setHint(nameHint, nameInput, false, msg); }
            else if(field === 'mhid'){ setHint(mhidHint, mhidInput, false, msg); }
            else if(field === 'feedback'){ setHint(textHint, textInput, false, msg); }
            else { if(formHint){ formHint.style.color = errColor; formHint.textContent = msg; setTimeout(()=>{ if(formHint) formHint.textContent=''; }, 2000); } }
            return;
          }
          if(formHint){ formHint.style.color = okColor; formHint.textContent = 'Thank you for your feedback!'; setTimeout(()=>{ if(formHint) formHint.textContent=''; }, 2000); }
          form.reset();
          if(nameHint) nameHint.textContent=''; if(mhidHint) mhidHint.textContent=''; if(textHint) textHint.textContent='';
        } catch(err){
          if(formHint){ formHint.style.color = errColor; formHint.textContent = 'Submission failed.'; setTimeout(()=>{ if(formHint) formHint.textContent=''; }, 2000); }
        }
      });
    }
  })();
  // Rating slider live value
  (function(){
    const slider = document.getElementById('fb_rating');
    const out = document.getElementById('fb_rating_value');
    if(!slider || !out) return;
    const update = () => {
      const min = parseFloat(slider.min || '1');
      const max = parseFloat(slider.max || '5');
      const val = parseFloat(slider.value || '3');
      const pct = ((val - min) / (max - min)) * 100;
      out.textContent = val + ' / ' + max;
      const clamped = Math.max(0, Math.min(100, pct));
      slider.style.background = `linear-gradient(to right, #a34bff 0%, #a34bff ${clamped}%, #ffffff ${clamped}%, #ffffff 100%)`;
    };
    slider.addEventListener('input', update);
    update();
  })();
  </script>
</body>
</html>
