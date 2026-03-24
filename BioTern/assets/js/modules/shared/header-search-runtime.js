"use strict";

(function (global) {
    function HeaderSearchRuntime(options) {
        this.options = options || {};
        this.styleId = this.options.styleId || "biotern-search-core-style";
        this.recentKey = this.options.recentKey || "biotern_recent_pages";
        this.maxResults = typeof this.options.maxResults === "number" ? this.options.maxResults : 8;
        this.maxRecent = typeof this.options.maxRecent === "number" ? this.options.maxRecent : 6;
    }

    HeaderSearchRuntime.prototype.normalize = function (value) {
        return String(value || "").toLowerCase().trim();
    };

    HeaderSearchRuntime.prototype.escapeRegex = function (value) {
        return String(value || "").replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    };

    HeaderSearchRuntime.prototype.escapeHtml = function (value) {
        return String(value || "").replace(/[&<>"']/g, function (ch) {
            return {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                "\"": "&quot;",
                "'": "&#39;"
            }[ch];
        });
    };

    HeaderSearchRuntime.prototype.highlight = function (text, query) {
        if (!query) {
            return this.escapeHtml(text);
        }
        var re = new RegExp("(" + this.escapeRegex(query) + ")", "ig");
        return this.escapeHtml(text).replace(re, "<mark>$1</mark>");
    };

    HeaderSearchRuntime.prototype.readStorageJson = function (key, fallbackValue) {
        try {
            var raw = global.localStorage.getItem(key);
            if (!raw) {
                return fallbackValue;
            }
            var parsed = JSON.parse(raw);
            return parsed == null ? fallbackValue : parsed;
        } catch (err) {
            return fallbackValue;
        }
    };

    HeaderSearchRuntime.prototype.writeStorageJson = function (key, value) {
        try {
            global.localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (err) {
            return false;
        }
    };

    HeaderSearchRuntime.prototype.ensureStyle = function () {
        if (document.getElementById(this.styleId)) {
            return;
        }

        var style = document.createElement("style");
        style.id = this.styleId;
        style.textContent =
            ".biotern-search-hint{padding:8px 12px;color:#8ea1c0;font-size:11px;border-bottom:1px solid rgba(255,255,255,.08)}" +
            ".biotern-search-results{max-height:260px;overflow:auto;padding:6px 0}" +
            ".biotern-search-section{padding:6px 12px 4px;color:#7f92b3;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}" +
            ".biotern-search-item{display:flex;align-items:center;gap:10px;padding:8px 12px;color:#dbe7ff;text-decoration:none}" +
            ".biotern-search-item:hover,.biotern-search-item.active{background:rgba(52,84,209,.18);color:#fff}" +
            ".biotern-search-item i{width:16px;text-align:center;opacity:.9}" +
            ".biotern-search-item mark{padding:0 2px;border-radius:2px;background:rgba(255,162,29,.28);color:#fff}" +
            ".biotern-search-empty{padding:10px 12px;color:#8ea1c0;font-size:12px}";
        document.head.appendChild(style);
    };

    HeaderSearchRuntime.prototype.getSectionName = function (link) {
        var item = link ? link.closest(".nxl-item") : null;
        while (item) {
            var prev = item.previousElementSibling;
            while (prev) {
                if (prev.classList && prev.classList.contains("nxl-caption")) {
                    var text = (prev.textContent || "").trim();
                    return text || "Other";
                }
                prev = prev.previousElementSibling;
            }
            var parent = item.parentElement;
            item = parent ? parent.closest(".nxl-item") : null;
        }
        return "Other";
    };

    HeaderSearchRuntime.prototype.getTopIconClass = function (link) {
        var item = link ? link.closest(".nxl-item") : null;
        if (!item) {
            return "feather-file";
        }

        var current = item;
        var parent = current.parentElement;
        while (parent && parent.classList && !parent.classList.contains("nxl-navbar")) {
            var parentItem = parent.closest(".nxl-item");
            if (!parentItem) {
                break;
            }
            current = parentItem;
            parent = current.parentElement;
        }

        var direct = current.querySelector(":scope > .nxl-link .nxl-micon i");
        if (direct && direct.className) {
            return direct.className;
        }
        var any = current.querySelector(".nxl-micon i");
        return any && any.className ? any.className : "feather-file";
    };

    HeaderSearchRuntime.prototype.collectNavigationLinks = function () {
        var self = this;
        var raw = Array.prototype.slice.call(document.querySelectorAll(".nxl-navigation a.nxl-link[href]"));
        var dedupe = {};
        return raw
            .map(function (link) {
                return {
                    href: link.getAttribute("href") || "",
                    text: (link.textContent || "").trim(),
                    section: self.getSectionName(link),
                    icon: self.getTopIconClass(link)
                };
            })
            .filter(function (item) {
                if (!item.href || item.href === "#" || item.href.indexOf("javascript:") === 0 || !item.text) {
                    return false;
                }
                var key = self.normalize(item.href);
                if (dedupe[key]) {
                    return false;
                }
                dedupe[key] = true;
                return true;
            });
    };

    HeaderSearchRuntime.prototype.getRecent = function () {
        var value = this.readStorageJson(this.recentKey, []);
        return Array.isArray(value) ? value : [];
    };

    HeaderSearchRuntime.prototype.saveRecent = function (href) {
        if (!href) {
            return;
        }
        var recent = this.getRecent().filter(function (value) {
            return value !== href;
        });
        recent.unshift(href);
        recent = recent.slice(0, this.maxRecent);
        this.writeStorageJson(this.recentKey, recent);
    };

    HeaderSearchRuntime.prototype.scoredMatches = function (query, links) {
        var self = this;
        var q = this.normalize(query);
        if (!q) {
            return [];
        }

        return links
            .map(function (item) {
                var t = self.normalize(item.text);
                var s = self.normalize(item.section);
                var h = self.normalize(item.href);
                var score = 99;
                if (t.indexOf(q) === 0) {
                    score = 0;
                } else if (t.indexOf(q) !== -1) {
                    score = 1;
                } else if (s.indexOf(q) !== -1) {
                    score = 2;
                } else if (h.indexOf(q) !== -1) {
                    score = 3;
                }
                return Object.assign({ score: score }, item);
            })
            .filter(function (item) {
                return item.score < 90;
            })
            .sort(function (a, b) {
                if (a.score !== b.score) {
                    return a.score - b.score;
                }
                return a.text.localeCompare(b.text);
            })
            .slice(0, this.maxResults);
    };

    HeaderSearchRuntime.prototype.fromRecent = function (links) {
        var recent = this.getRecent();
        var out = [];
        for (var i = 0; i < recent.length; i += 1) {
            var href = recent[i];
            var match = links.find(function (item) {
                return item.href === href;
            });
            if (match) {
                out.push(match);
            }
        }
        return out;
    };

    HeaderSearchRuntime.prototype.buildSearchForm = function () {
        var form = document.createElement("div");
        form.className = "input-group search-form";
        form.innerHTML =
            '<span class="input-group-text"><i class="feather-search fs-6 text-muted"></i></span>' +
            '<input type="text" class="form-control search-input-field" placeholder="Search page...">' +
            '<span class="input-group-text"><button type="button" class="btn-close"></button></span>';
        return form;
    };

    HeaderSearchRuntime.prototype.groupBySection = function (items) {
        var groups = {};
        items.forEach(function (item) {
            var key = item.section || "Other";
            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(item);
        });
        return groups;
    };

    HeaderSearchRuntime.prototype.mountDropdown = function (dropdown, links) {
        var self = this;
        var host = dropdown.cloneNode(true);
        dropdown.parentNode.replaceChild(host, dropdown);

        var toggle = host.querySelector(".nxl-head-link");
        var menu = host.querySelector(".nxl-search-dropdown, .dropdown-menu");
        if (!toggle || !menu) {
            return;
        }

        toggle.removeAttribute("data-bs-toggle");
        toggle.removeAttribute("data-bs-auto-close");
        host.style.position = "relative";

        var searchForm = this.buildSearchForm();
        var input = searchForm.querySelector(".search-input-field");
        var clearBtn = searchForm.querySelector(".btn-close");

        var hint = document.createElement("div");
        hint.className = "biotern-search-hint";
        hint.innerHTML = "<strong>Search</strong> pages &nbsp; <span style=\"opacity:.9\">?/? navigate, Enter open, Esc close</span>";

        var results = document.createElement("div");
        results.className = "biotern-search-results";

        menu.classList.add("nxl-search-dropdown");
        menu.style.display = "none";
        menu.innerHTML = "";
        menu.appendChild(searchForm);
        menu.appendChild(hint);
        menu.appendChild(results);

        var current = [];
        var active = -1;

        function closeMenu() {
            menu.style.display = "none";
            menu.classList.remove("show");
            toggle.classList.remove("show");
        }

        function openMenu() {
            menu.style.display = "block";
            menu.classList.add("show");
            toggle.classList.add("show");
            global.setTimeout(function () {
                input.focus();
            }, 20);
            render(input.value);
        }

        function setActive(index) {
            var items = results.querySelectorAll(".biotern-search-item");
            items.forEach(function (item) {
                item.classList.remove("active");
            });
            if (index < 0 || index >= items.length) {
                active = -1;
                return;
            }
            active = index;
            items[index].classList.add("active");
            items[index].scrollIntoView({ block: "nearest" });
        }

        function go(href) {
            self.saveRecent(href);
            global.location.href = href;
        }

        function render(value) {
            var query = self.normalize(value);
            var list = query ? self.scoredMatches(value, links) : self.fromRecent(links);
            current = list;
            active = -1;
            results.innerHTML = "";

            if (!list.length) {
                var empty = document.createElement("div");
                empty.className = "biotern-search-empty";
                empty.innerHTML = query
                    ? "No results for <strong>" + self.escapeHtml(value) + "</strong>"
                    : "Start typing to search pages";
                results.appendChild(empty);
                return;
            }

            var grouped = self.groupBySection(list);
            Object.keys(grouped).forEach(function (section) {
                var heading = document.createElement("div");
                heading.className = "biotern-search-section";
                heading.textContent = section;
                results.appendChild(heading);

                grouped[section].forEach(function (item) {
                    var index = current.indexOf(item);
                    var link = document.createElement("a");
                    link.href = item.href;
                    link.className = "biotern-search-item";
                    link.setAttribute("data-index", String(index));
                    link.innerHTML =
                        '<i class="' + self.escapeHtml(item.icon) + '"></i>' +
                        "<span>" + self.highlight(item.text, value) + "</span>";
                    link.addEventListener("click", function (event) {
                        event.preventDefault();
                        go(item.href);
                    });
                    results.appendChild(link);
                });
            });
        }

        toggle.addEventListener("click", function (event) {
            event.preventDefault();
            if (menu.style.display === "block") {
                closeMenu();
            } else {
                openMenu();
            }
        });

        input.addEventListener("input", function () {
            render(input.value);
        });

        input.addEventListener("keydown", function (event) {
            var max = current.length - 1;
            if (event.key === "ArrowDown") {
                event.preventDefault();
                setActive(active < max ? active + 1 : 0);
                return;
            }
            if (event.key === "ArrowUp") {
                event.preventDefault();
                setActive(active > 0 ? active - 1 : max);
                return;
            }
            if (event.key === "Escape") {
                event.preventDefault();
                closeMenu();
                return;
            }
            if (event.key !== "Enter") {
                return;
            }
            event.preventDefault();
            if (active >= 0 && current[active]) {
                go(current[active].href);
                return;
            }
            if (current[0]) {
                go(current[0].href);
            }
        });

        clearBtn.addEventListener("click", function (event) {
            event.preventDefault();
            input.value = "";
            render("");
            input.focus();
        });

        document.addEventListener("click", function (event) {
            if (!host.contains(event.target)) {
                closeMenu();
            }
        });
    };

    HeaderSearchRuntime.prototype.init = function () {
        this.ensureStyle();
        var links = this.collectNavigationLinks();
        var dropdowns = Array.prototype.slice.call(document.querySelectorAll(".nxl-header-search"));
        if (!dropdowns.length) {
            return;
        }
        for (var i = 0; i < dropdowns.length; i += 1) {
            this.mountDropdown(dropdowns[i], links);
        }
    };

    function boot() {
        if (global.__bioternHeaderSearchCoreInit) {
            return;
        }
        global.__bioternHeaderSearchCoreInit = true;

        var runtime = new HeaderSearchRuntime();
        if (
            global.BioTernUiStateCore &&
            global.BioTernUiStateCore.dom &&
            typeof global.BioTernUiStateCore.dom.onReady === "function"
        ) {
            global.BioTernUiStateCore.dom.onReady(function () {
                runtime.init();
            });
            return;
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function () {
                runtime.init();
            });
        } else {
            runtime.init();
        }
    }

    boot();
})(window);

