document.addEventListener("DOMContentLoaded", () => {
  const helpIcon = document.getElementById("doroto-floating-help-icon");
  const dropdown = document.getElementById("doroto-help-dropdown");
  const ajaxurl =
    typeof dorotoAjax !== "undefined"
      ? dorotoAjax.ajaxurl
      : "/wp-admin/admin-ajax.php";
  const filter_input = 1;
  const special_group_output = 0;

  if (helpIcon && dropdown) {
    helpIcon.addEventListener("click", function () {
      console.log("Help icon clicked");
      dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
    });

    document.addEventListener("click", function (event) {
      if (!helpIcon.contains(event.target)) {
        dropdown.style.display = "none";
      }
    });

    // Tour create tournament
    const tour_create_tournament = (tournamentId, resumeStep = 0) => {
      console.log("Starting tour_create_tournament resumeStep:", resumeStep);
      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
        console.log("Tour started successfully.");
      });

      if (Number(resumeStep) === 0) {
        callCreateTournamentRecord();
      }

      if (Number(resumeStep) === 1) {
        //Step 2
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_create_tournament}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 2;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 2) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_create_tournament}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 3;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        //Step 4
        tour.addStep({
          title: dorotoTranslations.doroto_text_create_tournament,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br> ${dorotoTranslations.doroto_text_tournament_type}`,
          attachTo: {
            element: "#doroto-add-tournament-tournament-type",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 3;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_create_tournament,
          text: dorotoTranslations.doroto_text_submit_button,
          attachTo: {
            element: "#doroto-add-tournament-submit-button",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_create_tournament,
          text: dorotoTranslations.doroto_text_tournament_list,
          attachTo: {
            element: "#doroto-tournament-list",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_create_tournament,
          text: dorotoTranslations.doroto_text_tournament_selected,
          attachTo: {
            element: "#doroto-tournament-selected",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_create_tournament,
          text: dorotoTranslations.doroto_text_list_name,
          attachTo: {
            element: "#doroto-tournament-selected-name",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                const resumeStep = 4;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      //step 1
      if (Number(resumeStep) === 0 || Number(resumeStep) === 1) {
        console.log("Running tour_login_logout with resumeStep:", resumeStep);
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_create_tournament}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 1;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      console.log("Starting the tour...");
      tour.start();
      console.log("Tour start initiated.");
    };

    // Tour for login/logout
    const tour_login_logout = (tournamentId, resumeStep = 10) => {
      console.log("Starting tour_login_logout with resumeStep:", resumeStep);
      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
      });

      if (Number(resumeStep) === 10) {
        callCreateTournamentRecord();
      }

      if (Number(resumeStep) === 11) {
        //Step 2
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_login_logout}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 12;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 12) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_login_logout}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "bottom",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 13;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_login_logout,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br> ${dorotoTranslations.doroto_text_login_table}`,
          attachTo: {
            element: "#doroto-tournament-selected-login",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_login_logout,
          text: dorotoTranslations.doroto_text_list_name,
          attachTo: {
            element: "#doroto-tournament-selected-name",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_login_logout,
          text: dorotoTranslations.doroto_text_list_count,
          attachTo: {
            element: "#doroto-tournament-selected-count",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_login_logout,
          text: dorotoTranslations.doroto_text_example_1_login,
          attachTo: {
            element: "#doroto-link-login-logout",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_login_logout,
          text: dorotoTranslations.doroto_text_list_players,
          attachTo: {
            element: "#doroto-table-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                const resumeStep = 12;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      console.log(
        "login_logout: Before condition check, resumeStep:",
        resumeStep
      );
      if (Number(resumeStep) === 10 || Number(resumeStep) === 11) {
        console.log("Running tour_login_logout with resumeStep:", resumeStep);
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_login_logout}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 11;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      tour.start();
    };

    // Tour for basic settings
    const tour_tournament_setting_basic = (tournamentId, resumeStep = 20) => {
      console.log(
        "Starting tour_tournament_setting_basic resumeStep:",
        resumeStep
      );
      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
      });

      if (Number(resumeStep) === 20) {
        callCreateTournamentRecord();
      }
      //Step 2
      if (Number(resumeStep) === 21) {
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_tournament_management_basic}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 22;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 22) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_tournament_management_basic}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 23;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        //Step 4
        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_basic,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br>${dorotoTranslations.doroto_text_login_table}<br><br> ${dorotoTranslations.doroto_text_login_not_active}`,
          attachTo: {
            element: "#doroto-tournament-selected-login",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_basic,
          text: `${dorotoTranslations.doroto_text_other_option}<br><br> ${dorotoTranslations.doroto_text_example_1_add_players}`,
          attachTo: {
            element: "#doroto-player-add",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_basic,
          text: dorotoTranslations.doroto_text_list_registration_status,
          attachTo: {
            element: "#doroto-tournament-selected-registration-status",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_basic,
          text: `${dorotoTranslations.doroto_text_other_option}<br><br> ${dorotoTranslations.doroto_text_list_registration_status}`,
          attachTo: {
            element: "#doroto-invitation-registration-status",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_basic,
          text: dorotoTranslations.doroto_text_list_organizer,
          attachTo: {
            element: "#doroto-tournament-selected-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_basic,
          text: dorotoTranslations.doroto_text_list_tournament_status,
          attachTo: {
            element: "#doroto-tournament-selected-tournament-status",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_basic,
          text: `${dorotoTranslations.doroto_text_other_option}<br><br> ${dorotoTranslations.doroto_text_list_tournament_status}`,
          attachTo: {
            element: "#doroto-invitation-registration-status",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                const resumeStep = 22;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      //step 1
      if (Number(resumeStep) === 20 || Number(resumeStep) === 21) {
        console.log(
          "Running tour_create_tournament with resumeStep:",
          resumeStep
        );
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_tournament_management_basic}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 21;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      tour.start();
    };

    // Tour for advance settings
    const tour_tournament_setting_advance = (tournamentId, resumeStep = 30) => {
      console.log(
        "Starting tour_tournament_setting_advance resumeStep:",
        resumeStep
      );
      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
      });

      if (Number(resumeStep) === 30) {
        callCreateTournamentRecord();
      }

      if (Number(resumeStep) === 31) {
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_tournament_management_advance}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 32;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 32) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_tournament_management_advance}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 33;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        //Step 4

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_advance,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br> ${dorotoTranslations.doroto_text_advance_settings_introduction}`,
          attachTo: {
            element: "#doroto-tournament-editing",
            on: "left",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_advance,
          text: dorotoTranslations.doroto_text_settings_name_courts,
          attachTo: {
            element: "#doroto_name_type_courts",
            on: "left",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_advance,
          text: dorotoTranslations.doroto_text_settings_match_result,
          attachTo: {
            element: "#doroto_settings_match_result",
            on: "left",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_advance,
          text: dorotoTranslations.doroto_settings_special_group,
          attachTo: {
            element: "#doroto_settings_special_group",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_advance,
          text: dorotoTranslations.doroto_text_settings_save_button,
          attachTo: {
            element: "#doroto-settings-save-button",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_tournament_management_advance,
          text: dorotoTranslations.doroto_text_settings_organizer_rights,
          attachTo: {
            element: "#doroto-settings-organizer-rights",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                const resumeStep = 32;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 30 || Number(resumeStep) === 31) {
        console.log(
          "Running tour_create_tournament with resumeStep:",
          resumeStep
        );
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_tournament_management_advance}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 31;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }
      tour.start();
      tour.show(resumeStep);
    };

    // Tour tournament_example_1
    const tour_tournament_example_1 = (tournamentId, resumeStep = 40) => {
      console.log("Starting tour_tournament_example_1 resumeStep:", resumeStep);

      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
        exitOnEsc: false,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
      });

      if (Number(resumeStep) === 40) {
        callCreateTournamentRecord();
      }

      if (Number(resumeStep) === 41) {
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_1}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 42;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 42) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_1}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 43;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br> ${dorotoTranslations.doroto_text_example_1_players}<br><br> ${dorotoTranslations.doroto_text_example_1_registration_open}`,
          attachTo: {
            element: "#doroto-table-player-name",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_login,
          attachTo: {
            element: "#doroto-link-login-logout",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_organizer,
          attachTo: {
            element: "#doroto_invitation_organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_state,
          attachTo: {
            element: "#doroto-table-player-state",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_payment,
          attachTo: {
            element: "#doroto-table-player-payment",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_player_management,
          attachTo: {
            element: "#doroto-player-management",
            on: "bottom-start",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_suspension,
          attachTo: {
            element: "#doroto-player-suspension",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_add_players,
          attachTo: {
            element: "#doroto-player-add",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_login_recommendation,
          attachTo: {
            element: "#doroto-link-login-logout",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_special_group,
          attachTo: {
            element: "#doroto-player-special",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_1,
          text: dorotoTranslations.doroto_text_example_1_add_payment,
          attachTo: {
            element: "#doroto-player-payments",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                const resumeStep = 42;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 40 || Number(resumeStep) === 41) {
        console.log(
          "Running tour_tournament_example_1 with resumeStep:",
          resumeStep
        );
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_1}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 41;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      tour.start();
    };

    // Tour tournament_example_2
    const tour_tournament_example_2 = (tournamentId, resumeStep = 50) => {
      console.log("Starting tour_tournament_example_2 resumeStep:", resumeStep);

      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
        exitOnEsc: false,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
      });

      if (Number(resumeStep) === 50) {
        callCreateTournamentRecord();
      }

      if (Number(resumeStep) === 51) {
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_2}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 52;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 52) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_2}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 53;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        //Step 4

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br> ${dorotoTranslations.doroto_text_example_1_players}<br><br> ${dorotoTranslations.doroto_text_example_1_registration_closed}`,
          attachTo: {
            element: "#doroto-table-player-name",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_match_count,
          attachTo: {
            element: "#doroto-table-player-match-count",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_winner_minimum,
          attachTo: {
            element: "#doroto-settings-winner-minimum",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_won,
          attachTo: {
            element: "#doroto-table-player-won",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_lost,
          attachTo: {
            element: "#doroto-table-player-lost",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_ratio,
          attachTo: {
            element: "#doroto-table-player-ratio",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_trend,
          attachTo: {
            element: "#doroto-table-player-trend",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_residue,
          attachTo: {
            element: "#doroto-table-player-residue",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_tournament_progress,
          attachTo: {
            element: "#doroto-tournament-progress",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_variables_progress,
          attachTo: {
            element: "#doroto-variables-progress",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_games_to_play,
          attachTo: {
            element: "#doroto-games-to-play",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_games_to_play_id,
          attachTo: {
            element: "#doroto-games-to-play-id",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_games_to_play_l1,
          attachTo: {
            element: "#doroto-games-to-play-l1",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_games_to_play_p1,
          attachTo: {
            element: "#doroto-games-to-play-p1",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_games_to_play_l2,
          attachTo: {
            element: "#doroto-games-to-play-l2",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text: dorotoTranslations.doroto_text_example_2_games_to_play_p2,
          attachTo: {
            element: "#doroto-games-to-play-p2",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text:
            dorotoTranslations.doroto_text_example_2_games_to_play_hide +
            "<br>" +
            dorotoTranslations.doroto_text_example_2_games_to_play_only_admin,
          attachTo: {
            element: "#doroto-games-to-play-hide",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text:
            dorotoTranslations.doroto_text_example_2_games_to_play_result +
            "<br>" +
            dorotoTranslations.doroto_text_example_2_games_to_play_only_admin,
          attachTo: {
            element: "#doroto-games-to-play-result",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_2,
          text:
            dorotoTranslations.doroto_text_example_2_games_to_play_save +
            "<br>" +
            dorotoTranslations.doroto_text_example_2_games_to_play_only_admin,
          attachTo: {
            element: "#doroto-games-to-play-save",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                const resumeStep = 52;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 50 || Number(resumeStep) === 51) {
        console.log(
          "Running tour_tournament_example_2 with resumeStep:",
          resumeStep
        );
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_2}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 51;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      tour.start();
    };

    // Tour tournament_example_3
    const tour_tournament_example_3 = (tournamentId, resumeStep = 60) => {
      console.log("Starting tour_tournament_example_3 resumeStep:", resumeStep);

      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
        exitOnEsc: false,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
      });

      if (Number(resumeStep) === 60) {
        callCreateTournamentRecord();
      }

      if (Number(resumeStep) === 61) {
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_3}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 62;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 62) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_3}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 63;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        //Step 4

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br> ${dorotoTranslations.doroto_text_example_3_players}<br><br> ${dorotoTranslations.doroto_text_example_1_registration_closed}`,
          attachTo: {
            element: "#doroto-table-player-name",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_winner_ideal_couple,
          attachTo: {
            element: "#doroto-winner-ideal-couple",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_winner_best_player,
          attachTo: {
            element: "#doroto-winner-best-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_match_count,
          attachTo: {
            element: "#doroto-table-player-match-count",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_state,
          attachTo: {
            element: "#doroto-table-player-state",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_ratio,
          attachTo: {
            element: "#doroto-table-player-ratio",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_residue,
          attachTo: {
            element: "#doroto-table-player-residue",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_suspended,
          attachTo: {
            element: "#doroto-settings-winner-suspended",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_played_matches,
          attachTo: {
            element: "#doroto-played-matches-table",
            on: "left",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_filter,
          attachTo: {
            element: "#doroto-filter-by-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_statistical_data,
          attachTo: {
            element: "#doroto-statistical-data",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_3,
          text: dorotoTranslations.doroto_text_example_3_change_match_result,
          attachTo: {
            element: "#doroto-change-match-result",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                const resumeStep = 62;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 60 || Number(resumeStep) === 61) {
        console.log(
          "Running tour_tournament_example_3 with resumeStep:",
          resumeStep
        );
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_3}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 61;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      tour.start();
    };

    // Tour tournament_example_4
    const tour_tournament_example_4 = (
      tournamentId,
      resumeStep = 70,
      filter_input
    ) => {
      console.log("Starting tour_tournament_example_4 resumeStep:", resumeStep);

      const tour = new Shepherd.Tour({
        defaultStepOptions: {
          scrollTo: true,
          cancelIcon: {
            enabled: true,
          },
        },
        useModalOverlay: true,
        exitOnEsc: false,
      });

      tour.on("start", () => {
        console.log("Starting the tour...");
        doroto_openAllContainers(resumeStep);
      });

      if (Number(resumeStep) === 70) {
        callCreateTournamentRecord();
      }

      if (Number(resumeStep) === 71) {
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_4}`,
          text: dorotoTranslations.doroto_text_force_to_login_message,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                doroto_set_player_filter_help(tournamentId, 1, 1);
                const resumeStep = 72;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
                doroto_openAllContainers(resumeStep);
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 72) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_4}`,
          text: dorotoTranslations.doroto_text_force_to_login,

          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_register,
              action: () => {
                window.location.href = "/wp-login.php?action=register";
              },
            },
            {
              text: dorotoTranslations.doroto_text_login,
              action: () => {
                window.location.href = "/wp-login.php";
              },
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 73;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
                doroto_openAllContainers(resumeStep);
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 73) {
        //Step 3
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_4}`,
          text: `${dorotoTranslations.doroto_text_example_4_preparation_filter}<br><br> ${dorotoTranslations.doroto_text_example_4_sorry_login}`,

          attachTo: {
            element: "#doroto-filter-by-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 74;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        //Step 4

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: `${dorotoTranslations.doroto_text_finally_help}<br><br> ${dorotoTranslations.doroto_text_example_4_players}<br>${dorotoTranslations.doroto_text_example_1_registration_closed}<br><br>${dorotoTranslations.doroto_text_example_4_closed_tournament}`,

          attachTo: {
            element: "#doroto-games-to-play",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_match_count,
          attachTo: {
            element: "#doroto-table-player-match-count",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_2_special,
          attachTo: {
            element: "#doroto_tournament_parameters_two_special_group",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_2non_special,
          attachTo: {
            element: "#doroto_tournament_parameters_two_out_group",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_filter_woman,
          attachTo: {
            element: "#doroto-filter-by-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_special_games,
          attachTo: {
            element: "#doroto-played-matches-table",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_statistics_woman,
          attachTo: {
            element: "#doroto-statistical-data",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_statistics_player,
          attachTo: {
            element: "#doroto-statistical-data-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_statistics_teammate_L,
          attachTo: {
            element: "#doroto-statistical-data-teammate-L",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_statistics_teammate_P,
          attachTo: {
            element: "#doroto-statistical-data-teammate-R",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_statistics_opponent,
          attachTo: {
            element: "#doroto-statistical-data-opponent",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_statistics_total,
          attachTo: {
            element: "#doroto-statistical-data-total",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: `${dorotoTranslations.doroto_text_example_4_statistics_woman_final}<br><br> ${dorotoTranslations.doroto_text_example_4_statistics_round_final}`,
          attachTo: {
            element: "#doroto-statistical-data",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_statistics_residue,
          attachTo: {
            element: "#doroto-table-player-residue",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                doroto_set_player_filter_help(tournamentId, 1, 0);
                const resumeStep = 74;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }
      if (Number(resumeStep) === 74) {
        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_filter_man,
          attachTo: {
            element: "#doroto-filter-by-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_reload_message,
          attachTo: {
            element: "#doroto-filter-by-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_reload,
              action: () => {
                const resumeStep = 75;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }
      if (Number(resumeStep) === 75) {
        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: `${dorotoTranslations.doroto_text_example_4_man_selected}<br><br> ${dorotoTranslations.doroto_text_example_4_sorry_login}`,
          attachTo: {
            element: "#doroto-filter-by-player",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: () => {
                const resumeStep = 76;
                localStorage.setItem("resumeStep", resumeStep);
                tour.next();
              },
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_man_matches,
          attachTo: {
            element: "#doroto-played-matches-table",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_man_statistics,
          attachTo: {
            element: "#doroto-statistical-data",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: dorotoTranslations.doroto_text_example_4_last_word,
          attachTo: {
            element: "#doroto_tournament_parameters_announce_round_end",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_next,
              action: tour.next,
            },
          ],
        });

        tour.addStep({
          title: dorotoTranslations.doroto_text_example_4,
          text: `${dorotoTranslations.doroto_text_example_4_last_word_filter}<br><br>${dorotoTranslations.doroto_text_reload_message}`,
          attachTo: {
            element: "#doroto_tournament_parameters_announce_round_end",
            on: "top",
          },
          scrollTo: true,
          scrollToHandler: (element) => {
            element.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
          },

          buttons: [
            {
              text: dorotoTranslations.doroto_text_back,
              action: tour.back,
            },
            {
              text: dorotoTranslations.doroto_text_close,
              action: () => {
                doroto_set_player_filter_help(tournamentId, 0, 0);
                const resumeStep = 77;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.delete("resume_step");
                currentUrl.searchParams.delete("nocache");
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      if (Number(resumeStep) === 70 || Number(resumeStep) === 71) {
        console.log(
          "Running tour_tournament_example_1 with resumeStep:",
          resumeStep
        );
        tour.addStep({
          title: `${dorotoTranslations.doroto_text_preparing_help}: ${dorotoTranslations.doroto_text_example_4}`,
          text: `${dorotoTranslations.doroto_text_redirect_message}<br><br>${dorotoTranslations.doroto_text_redirect_message_extended.replace('%s', tournamentId)}`,
          attachTo: {
            element: "#doroto-tournament-name-and-organizer",
            on: "top",
          },
          buttons: [
            {
              text: dorotoTranslations.doroto_text_cancel,
              action: tour.cancel,
            },
            {
              text: dorotoTranslations.doroto_text_redirect,
              action: () => {
                const resumeStep = 71;
                doroto_AddAdminToTournament(tournamentId);
                console.log("After doroto_AddAdminToTournament");
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set("tournament_id", tournamentId);
                currentUrl.searchParams.set("resume_step", resumeStep);
                currentUrl.searchParams.set("nocache", new Date().getTime());
                window.location.href = currentUrl.toString();
              },
            },
          ],
        });
      }

      tour.start();
    };

    document.querySelectorAll(".doroto-help-tour").forEach((link) => {
      link.addEventListener("click", function (event) {
        event.preventDefault();

        const tourName = event.target.getAttribute("doroto-data-tour");
        const tournamentId = event.target.getAttribute("data-tournament-id");
        console.log("tour name:", tourName);
        console.log("tournament id:", tournamentId);

        if (!tournamentId) {
          console.log("Tournament ID is not available.");
          return;
        }

        if (tourName === "tour_create_tournament") {
          tour_create_tournament(tournamentId);
        } else if (tourName === "tour_login_logout") {
          tour_login_logout(tournamentId);
        } else if (tourName === "tour_tournament_setting_basic") {
          tour_tournament_setting_basic(tournamentId);
        } else if (tourName === "tour_tournament_setting_advance") {
          tour_tournament_setting_advance(tournamentId);
        } else if (tourName === "tour_tournament_example_1") {
          tour_tournament_example_1(tournamentId);
        } else if (tourName === "tour_tournament_example_2") {
          tour_tournament_example_2(tournamentId);
        } else if (tourName === "tour_tournament_example_3") {
          tour_tournament_example_3(tournamentId);
        } else if (tourName === "tour_tournament_example_4") {
          tour_tournament_example_4(tournamentId);
        } else {
          console.error(`Tour "${tourName}" not found.`);
        }
      });
    });

    const urlParams = new URLSearchParams(window.location.search);
    const tournamentId = urlParams.get("tournament_id");
    const resumeStep = parseInt(urlParams.get("resume_step"), 10);

    if (tournamentId && (Number(resumeStep) == 1 || Number(resumeStep) == 2)) {
      tour_create_tournament(tournamentId, resumeStep);
    }
    if (
      tournamentId &&
      (Number(resumeStep) == 11 || Number(resumeStep) == 12)
    ) {
      tour_login_logout(tournamentId, resumeStep);
    }
    if (
      tournamentId &&
      (Number(resumeStep) == 21 || Number(resumeStep) == 22)
    ) {
      tour_tournament_setting_basic(tournamentId, resumeStep);
    }
    if (
      tournamentId &&
      (Number(resumeStep) == 31 || Number(resumeStep) == 32)
    ) {
      tour_tournament_setting_advance(tournamentId, resumeStep);
    }
    if (
      tournamentId &&
      (Number(resumeStep) == 41 || Number(resumeStep) == 42)
    ) {
      tour_tournament_example_1(tournamentId, resumeStep);
    }
    if (
      tournamentId &&
      (Number(resumeStep) == 51 || Number(resumeStep) == 52)
    ) {
      tour_tournament_example_2(tournamentId, resumeStep);
    }
    if (
      tournamentId &&
      (Number(resumeStep) == 61 || Number(resumeStep) == 62)
    ) {
      tour_tournament_example_3(tournamentId, resumeStep);
    }
    if (tournamentId && Number(resumeStep) >= 71 && Number(resumeStep) < 76) {
      tour_tournament_example_4(tournamentId, resumeStep);
    }
  }
});

document.addEventListener("DOMContentLoaded", () => {
  const tourSeen = localStorage.getItem("tour_seen");
  const helpIconExists =
    document.querySelector("#doroto-floating-help-icon") !== null;
  if (!tourSeen && helpIconExists) {
    DorotoStartHelpIconTour();
    localStorage.setItem("tour_seen", "true");
  }
});

function DorotoStartHelpIconTour() {
  console.log("Starting the floating help icon tour.");

  const overlay = document.createElement("div");
  overlay.className = "shepherd-overlay";
  document.body.appendChild(overlay);

  const tour = new Shepherd.Tour({
    defaultStepOptions: {
      scrollTo: true,
    },
    useModalOverlay: true,
  });

  tour.addStep({
    title: dorotoTranslations.doroto_text_need_help,
    text: dorotoTranslations.doroto_text_floating_icon,
    attachTo: {
      element: "#doroto-floating-help-icon",
      on: "bottom",
    },
    buttons: [
      {
        text: dorotoTranslations.doroto_text_close,
        action: tour.complete,
      },
    ],
  });

  tour.start();
}

function callCreateTournamentRecord() {
  console.log("callCreateTournamentRecord starting");
  const ajaxurl =
    typeof dorotoAjax !== "undefined"
      ? dorotoAjax.ajaxurl
      : "/wp-admin/admin-ajax.php";
  fetch(dorotoAjax.ajaxurl + "?action=doroto_create_tournament_record", {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      nonce: typeof dorotoAjax !== "undefined" ? dorotoAjax.nonce : "",
    }),
  })
    .then(() => {
      console.log("Tournament record created without response.");
    })
    .catch((error) => {
      console.error("Error:", error);
    });
}

function doroto_AddAdminToTournament(tournamentId) {
  console.log(
    "doroto_AddAdminToTournament starting with tournamentId:",
    tournamentId
  );
  const ajaxurl =
    typeof dorotoAjax !== "undefined"
      ? dorotoAjax.ajaxurl
      : "/wp-admin/admin-ajax.php";

  fetch(ajaxurl + "?action=doroto_add_current_user_to_admin", {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "doroto_add_current_user_to_admin",
      tournament_id: tournamentId,
      nonce: typeof dorotoAjax !== "undefined" ? dorotoAjax.nonce : "",
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        console.log(
          "User successfully added as admin to the tournament:",
          tournamentId
        );
      } else {
        console.error("Error adding user as admin:", data.message);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
    });
}

function doroto_openAllContainers(resumeStep) {
  console.log("containers_Resume step:", resumeStep);
  jQuery(".doroto-content-container").slideDown();
}

function doroto_set_player_filter_help(
  tournamentId,
  filter_input,
  special_group_output
) {
  console.log(
    "doroto_set_player_filter_help starting with tournamentId:",
    tournamentId
  );
  const ajaxurl =
    typeof dorotoAjax !== "undefined"
      ? dorotoAjax.ajaxurl
      : "/wp-admin/admin-ajax.php";

  fetch(ajaxurl + "?action=doroto_player_filter_help", {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "doroto_player_filter_help",
      tournament_id: tournamentId,
      filter_input: filter_input,
      special_group_output: special_group_output,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        console.log("User successfully set player filter:", tournamentId);
      } else {
        console.error("Error set filter:", data.message);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
    });
}
