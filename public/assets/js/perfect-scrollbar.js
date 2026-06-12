(function () {
  var isWindows = navigator.platform.indexOf("Win") > -1;

  if (isWindows) {

    // Main panel
    const mainpanel = document.querySelector("main");
    if (mainpanel) {
      new PerfectScrollbar(mainpanel);
    }

    // overflow-auto
    document.querySelectorAll(".overflow-auto").forEach((element) => {
      new PerfectScrollbar(element);
    });

    // overflow-y-auto
    document.querySelectorAll(".overflow-y-auto").forEach((element) => {
      new PerfectScrollbar(element);
    });

    // overflow-x-auto
    document.querySelectorAll(".overflow-x-auto").forEach((element) => {
      new PerfectScrollbar(element);
    });

  }
})();