/*!
 * chartjs-plugin-autocolors v0.0.3
 * https://github.com/kurkle/chartjs-plugin-autocolors#readme
 * (c) 2020 Jukka Kurkela
 * Released under the MIT License
 */
!function (o, t) {
    "object" == typeof exports && "undefined" != typeof module ? module.exports = t() : "function" == typeof define && define.amd ? define(t) : (o = "undefined" != typeof globalThis ? globalThis : o || self)["chartjs-plugin-autocolors"] = t()
}(this, (function () {
    "use strict";

    /*!
     * @kurkle/color v0.1.9
     * https://github.com/kurkle/color#readme
     * (c) 2020 Jukka Kurkela
     * Released under the MIT License
     */
    function o(o) {
        return o + .5 | 0
    }

    const t = (o, t, n) => Math.max(Math.min(o, n), t);

    function n(n) {
        return t(o(255 * n), 0, 255)
    }

    function e(n) {
        return n && (n.a < 255 ? `rgba(${n.r}, ${n.g}, ${n.b}, ${function (n) {
            return t(o(n / 2.55) / 100, 0, 1)
        }(n.a)})` : `rgb(${n.r}, ${n.g}, ${n.b})`)
    }

    function r(o, t, n) {
        const e = (e, r = (e + o / 60) % 6) => n - n * t * Math.max(Math.min(r, 4 - r, 1), 0);
        return [e(5), e(3), e(1)]
    }

    function a(o, t, e) {
        return a = r, d = o, u = t, c = e, (Array.isArray(d) ? a(d[0], d[1], d[2]) : a(d, u, c)).map(n);
        var a, d, u, c
    }

    function d(o, t, n) {
        o.backgroundColor = o.backgroundColor || t, o.borderColor = o.borderColor || n
    }

    function u(o, t, n) {
        const e = o.next().value;
        return "function" == typeof t ? t(Object.assign({colors: e}, n)) : e
    }

    return {
        id: "autocolors", beforeUpdate(o, t) {
            const {mode: n = "dataset", enabled: r = !0, customize: c} = t;
            if (!r) return;
            const i = function* () {
                const o = function* () {
                    yield 0;
                    for (let o = 1; o < 10; o++) {
                        const t = 1 << o;
                        for (let o = 1; o <= t; o += 2) yield o / t
                    }
                }();
                let t = o.next();
                for (; !t.done;) {
                    let n = a(Math.round(360 * t.value), .6, .8);
                    yield{
                        background: e({r: n[0], g: n[1], b: n[2], a: 192}),
                        border: e({r: n[0], g: n[1], b: n[2], a: 144})
                    }, n = a(Math.round(360 * t.value), .6, .5), yield{
                        background: e({
                            r: n[0],
                            g: n[1],
                            b: n[2],
                            a: 192
                        }), border: e({r: n[0], g: n[1], b: n[2], a: 144})
                    }, t = o.next()
                }
            }();
            for (const t of o.data.datasets) if (!t.backgroundColor || !t.borderColor) if ("dataset" === n) {
                const n = u(i, c, {chart: o, datasetIndex: t.index});
                d(t, n.background, n.border)
            } else {
                const n = [], e = [];
                for (let r = 0; r < t.data.length; r++) {
                    const a = u(i, c, {chart: o, datasetIndex: t.index, dataIndex: r});
                    n.push(a.background), e.push(a.border)
                }
                d(t, n, e)
            }
        }
    }
}));
//# sourceMappingURL=chartjs-plugin-autocolors.min.js.map