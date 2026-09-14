/* =========================================================
   MEDACCESS — MAIN JAVASCRIPT
========================================================= */

document.addEventListener("DOMContentLoaded", () => {

    /* -----------------------------------------
       PAGE LOADER
    ----------------------------------------- */

    const loader = document.getElementById("pageLoader");

    window.addEventListener("load", () => {

        setTimeout(() => {

            loader?.classList.add("hidden");

        }, 450);

    });



    /* -----------------------------------------
       HEADER SCROLL EFFECT
    ----------------------------------------- */

    const header = document.getElementById("siteHeader");

    const updateHeader = () => {

        if (window.scrollY > 20) {

            header?.classList.add("scrolled");

        } else {

            header?.classList.remove("scrolled");

        }

    };

    updateHeader();

    window.addEventListener(
        "scroll",
        updateHeader,
        { passive: true }
    );



    /* -----------------------------------------
       MOBILE MENU
    ----------------------------------------- */

    const menuButton =
        document.getElementById("mobileMenuBtn");

    const mobileNav =
        document.getElementById("mobileNav");

    menuButton?.addEventListener("click", () => {

        mobileNav?.classList.toggle("open");

        menuButton.classList.toggle("active");

    });


    document
        .querySelectorAll(".mobile-nav a")
        .forEach(link => {

            link.addEventListener("click", () => {

                mobileNav?.classList.remove("open");

            });

        });



    /* -----------------------------------------
       SCROLL REVEAL
    ----------------------------------------- */

    const revealElements =
        document.querySelectorAll(".reveal");

    if ("IntersectionObserver" in window) {

        const observer =
            new IntersectionObserver(
                (entries, observer) => {

                    entries.forEach(entry => {

                        if (entry.isIntersecting) {

                            entry.target
                                .classList
                                .add("visible");

                            observer.unobserve(
                                entry.target
                            );

                        }

                    });

                },
                {
                    threshold: 0.12,
                    rootMargin: "0px 0px -50px 0px"
                }
            );

        revealElements.forEach(element => {

            observer.observe(element);

        });

    } else {

        revealElements.forEach(element => {

            element.classList.add("visible");

        });

    }



    /* -----------------------------------------
       EMERGENCY FORM
    ----------------------------------------- */

    const emergencyForm =
        document.getElementById("emergencyForm");

    const emergencyInput =
        document.getElementById("emergencyId");


    emergencyForm?.addEventListener(
        "submit",
        (event) => {

            event.preventDefault();

            const id =
                emergencyInput.value
                    .trim()
                    .toUpperCase();


            if (!id) {

                emergencyInput.focus();

                return;

            }


            /*
             * FRONTEND DEMO ONLY
             *
             * Later this will become:
             *
             * emergency.html?id=...
             *
             * and the PHP backend will securely
             * retrieve the patient's profile.
             */

            window.location.href =
                `emergency.html?id=${encodeURIComponent(id)}`;

        }
    );

    /* -----------------------------------------
       EMERGENCY TIMER DEMO
    ----------------------------------------- */

    const timer =
        document.getElementById("accessTimer");

    if (timer) {

        let totalSeconds = 14 * 60 + 32;

        setInterval(() => {

            if (totalSeconds <= 0) {

                totalSeconds =
                    14 * 60 + 32;

            }

            totalSeconds--;

            const minutes =
                Math.floor(
                    totalSeconds / 60
                );

            const seconds =
                totalSeconds % 60;

            timer.textContent =
                `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;

        }, 1000);

    }



    /* -----------------------------------------
       BUTTON RIPPLE EFFECT
    ----------------------------------------- */

    document
        .querySelectorAll(".btn")
        .forEach(button => {

            button.addEventListener(
                "click",
                function(event) {

                    const ripple =
                        document.createElement("span");

                    const rect =
                        this.getBoundingClientRect();

                    const size =
                        Math.max(
                            rect.width,
                            rect.height
                        );

                    ripple.style.width =
                        `${size}px`;

                    ripple.style.height =
                        `${size}px`;

                    ripple.style.left =
                        `${event.clientX - rect.left - size / 2}px`;

                    ripple.style.top =
                        `${event.clientY - rect.top - size / 2}px`;

                    ripple.className =
                        "button-ripple";

                    this.appendChild(ripple);

                    setTimeout(() => {

                        ripple.remove();

                    }, 600);

                }
            );

        });



    /* -----------------------------------------
       KEYBOARD ESCAPE
    ----------------------------------------- */

    document.addEventListener(
        "keydown",
        event => {

            if (event.key === "Escape") {

                mobileNav?.classList.remove("open");

            }

        }
    );

});