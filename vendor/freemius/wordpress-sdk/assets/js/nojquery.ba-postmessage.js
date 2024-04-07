/*!
 * jQuery postMessage - v0.5 - 9/11/2009
 * http://benalman.com/projects/jquery-postmessage-plugin/
 *
 * Copyright (c) 2009 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 *
 * Non-jQuery fork by Jeff Lee
 *
 * This fork consists of the following changes:
 * 1. Basic code cleanup and restructuring, for legibility.
 * 2. The `postMessage` and `receiveMessage` functions can be bound arbitrarily,
 *    in terms of both function names and object scope. Scope is specified by
 *    the the "this" context of NoJQueryPostMessageMixin();
 * 3. I've removed the check for Opera 9.64, which used `$.browser`. There were
 *    at least three different GitHub users requesting the removal of this
 *    "Opera sniff" on the original project's Issues page, so I figured this
 *    would be a relatively safe change.
 * 4. `postMessage` no longer uses `$.param` to serialize messages that are not
 *    strings. I actually prefer this structure anyway. `receiveMessage` does
 *    not implement a corresponding deserialization step, and as such it seems
 *    cleaner and more symmetric to leave both data serialization and
 *    deserialization to the client.
 * 5. The use of `$.isFunction` is replaced by a functionally-identical check.
 * 6. The `$:nomunge` YUI option is no longer necessary.
 */
function NoJQueryPostMessageMixin(n,e){var t,i,o,s,a,r=1;return window.postMessage?(window.addEventListener?(t=function(n){window.addEventListener("message",n,!1)},i=function(n){window.removeEventListener("message",n,!1)}):(t=function(n){window.attachEvent("onmessage",n)},i=function(n){window.detachEvent("onmessage",n)}),this[n]=function(n,e,t){e&&t.postMessage(n,e.replace(/([^:]+:\/\/[^\/]+).*/,"$1"))},this[e]=function(n,e,s){if(o&&(i(o),o=null),!n)return!1;o=t((function(t){switch(Object.prototype.toString.call(e)){case"[object String]":if(e!==t.origin)return!1;break;case"[object Function]":if(e(t.origin))return!1}n(t)}))}):(this[n]=function(n,e,t){e&&(t.location=e.replace(/#.*$/,"")+"#"+ +new Date+r+++"&"+n)},this[e]=function(n,e,t){s&&(clearInterval(s),s=null),n&&(t="number"==typeof e?e:"number"==typeof t?t:100,s=setInterval((function(){var e=document.location.hash,t=/^#?\d+&/;e!==a&&t.test(e)&&(a=e,n({data:e.replace(t,"")}))}),t))}),this}