/*
 * nojquery-postmessage by Jeff Lee
 * a non-jQuery fork of:
 *
 * jQuery postMessage - v0.5 - 9/11/2009
 * http://benalman.com/projects/jquery-postmessage-plugin/
 *
 * Copyright (c) 2009 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 */
function NoJQueryPostMessageMixin(g,a){var b,h,e,d,f,c=1;if(window.postMessage){if(window.addEventListener){b=function(i){window.addEventListener("message",i,false)};h=function(i){window.removeEventListener("message",i,false)}}else{b=function(i){window.attachEvent("onmessage",i)};h=function(i){window.detachEvent("onmessage",i)}}this[g]=function(i,k,j){if(!k){return}j.postMessage(i,k.replace(/([^:]+:\/\/[^\/]+).*/,"$1"))};this[a]=function(k,j,i){if(e){h(e);e=null}if(!k){return false}e=b(function(l){switch(Object.prototype.toString.call(j)){case"[object String]":if(j!==l.origin){return false}break;case"[object Function]":if(j(l.origin)){return false}break}k(l)})}}else{this[g]=function(i,k,j){if(!k){return}j.location=k.replace(/#.*$/,"")+"#"+(+new Date)+(c++)+"&"+i};this[a]=function(k,j,i){if(d){clearInterval(d);d=null}if(k){i=typeof j==="number"?j:typeof i==="number"?i:100;d=setInterval(function(){var m=document.location.hash,l=/^#?\d+&/;if(m!==f&&l.test(m)){f=m;k({data:m.replace(l,"")})}},i)}}}return this};