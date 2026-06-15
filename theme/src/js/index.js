import '../sass/style.scss'
// function getCookie(cname) {
//     let name = cname + "=";
//     let decodedCookie = decodeURIComponent(document.cookie);
//     let ca = decodedCookie.split(';');
//     for(let i = 0; i <ca.length; i++) {
//       let c = ca[i];
//       while (c.charAt(0) == ' ') {
//         c = c.substring(1);
//       }
//       if (c.indexOf(name) == 0) {
//         return c.substring(name.length, c.length);
//       }
//     }
//     return "";
// }
// function setCookie(cname, cvalue, exdays) {
//     const d = new Date();
//     d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
//     let expires = "expires="+d.toUTCString();
//     document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
// }
// let cookielang = getCookie("lang");
// let browserlang = (navigator.language || navigator.userLanguage).substring(0,2); 
// let pagelang = document.querySelector('meta[name="language"]').content;
// if (cookielang == "" && browserlang == "pt" && pagelang == "en") {
//     window.location.href = "en";   
//     setCookie("lang", "pt", 365);
// }
// if (cookielang == "" && browserlang == "pt" && pagelang == "en") {
//     window.location.href = "pt";   
//     setCookie("lang", "pt", 365);
// }
// if (cookielang == "en" && pagelang == "pt") {
//         // window.location.href = "pt";   
//         setCookie("lang", "pt", 365);
// }
// if (cookielang == "pt" && pagelang == "en") {
//     // window.location.href = "pt";   
//     setCookie("lang", "en", 365);
// }
