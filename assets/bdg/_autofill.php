// Auto-fill the invite code from the URL so the register form is pre-filled.
// Handles both routers: history (/register?inviteCode=X) and hash (#/register?invitationCode=X).
(function(){
  try{
    var h = window.location.hash || '';
    var s = window.location.search || '';
    var m = (h + '&' + s).match(/(?:invitationCode|inviteCode|invite_code|r_code)=([A-Za-z0-9]+)/);
    if(!m) return;
    var code = decodeURIComponent(m[1]);
    if(!code) return;
    try{ localStorage.setItem('couponNum', code); }catch(e){}
    try{ sessionStorage.setItem('couponNum', code); }catch(e){}
    try{ localStorage.setItem('inviteCode', code); }catch(e){}
    try{ sessionStorage.setItem('inviteCode', code); }catch(e){}
  }catch(e){}
})();
