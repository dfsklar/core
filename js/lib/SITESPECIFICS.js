export default class SiteSpecifics {
   fetchFormedURL() {
      if (window.flarum_autologin_referer)
        return window.flarum_autologin_referer;
      else
        return "https://formed.org/";
   }
}
