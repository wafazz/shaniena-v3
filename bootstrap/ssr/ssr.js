import { resolveComponent, unref, withCtx, createVNode, createTextVNode, toDisplayString, useSSRContext, createSSRApp, h } from "vue";
import { ssrRenderComponent, ssrInterpolate } from "vue/server-renderer";
import { Head, createInertiaApp } from "@inertiajs/vue3";
import { renderToString } from "@vue/server-renderer";
import createServer from "@inertiajs/vue3/server";
import * as CoreUI from "@coreui/vue";
import { CIcon } from "@coreui/icons-vue";
const _sfc_main = {
  __name: "Welcome",
  __ssrInlineRender: true,
  props: {
    laravel: String,
    php: String
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      const _component_CContainer = resolveComponent("CContainer");
      const _component_CRow = resolveComponent("CRow");
      const _component_CCol = resolveComponent("CCol");
      const _component_CCard = resolveComponent("CCard");
      const _component_CCardHeader = resolveComponent("CCardHeader");
      const _component_CCardBody = resolveComponent("CCardBody");
      const _component_CListGroup = resolveComponent("CListGroup");
      const _component_CListGroupItem = resolveComponent("CListGroupItem");
      const _component_CBadge = resolveComponent("CBadge");
      const _component_CAlert = resolveComponent("CAlert");
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Foundation" }, null, _parent));
      _push(ssrRenderComponent(_component_CContainer, { class: "py-5" }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(_component_CRow, { class: "justify-content-center" }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(ssrRenderComponent(_component_CCol, { md: 8 }, {
                    default: withCtx((_3, _push4, _parent4, _scopeId3) => {
                      if (_push4) {
                        _push4(ssrRenderComponent(_component_CCard, null, {
                          default: withCtx((_4, _push5, _parent5, _scopeId4) => {
                            if (_push5) {
                              _push5(ssrRenderComponent(_component_CCardHeader, null, {
                                default: withCtx((_5, _push6, _parent6, _scopeId5) => {
                                  if (_push6) {
                                    _push6(`<strong${_scopeId5}>Shaniena v3 — Foundation</strong>`);
                                  } else {
                                    return [
                                      createVNode("strong", null, "Shaniena v3 — Foundation")
                                    ];
                                  }
                                }),
                                _: 1
                              }, _parent5, _scopeId4));
                              _push5(ssrRenderComponent(_component_CCardBody, null, {
                                default: withCtx((_5, _push6, _parent6, _scopeId5) => {
                                  if (_push6) {
                                    _push6(`<p class="text-body-secondary"${_scopeId5}> Laravel ${ssrInterpolate(__props.laravel)} + Inertia + Vue 3 + CoreUI (Bootstrap 5). </p>`);
                                    _push6(ssrRenderComponent(_component_CListGroup, { flush: "" }, {
                                      default: withCtx((_6, _push7, _parent7, _scopeId6) => {
                                        if (_push7) {
                                          _push7(ssrRenderComponent(_component_CListGroupItem, null, {
                                            default: withCtx((_7, _push8, _parent8, _scopeId7) => {
                                              if (_push8) {
                                                _push8(`Laravel `);
                                                _push8(ssrRenderComponent(_component_CBadge, { color: "primary" }, {
                                                  default: withCtx((_8, _push9, _parent9, _scopeId8) => {
                                                    if (_push9) {
                                                      _push9(`${ssrInterpolate(__props.laravel)}`);
                                                    } else {
                                                      return [
                                                        createTextVNode(toDisplayString(__props.laravel), 1)
                                                      ];
                                                    }
                                                  }),
                                                  _: 1
                                                }, _parent8, _scopeId7));
                                              } else {
                                                return [
                                                  createTextVNode("Laravel "),
                                                  createVNode(_component_CBadge, { color: "primary" }, {
                                                    default: withCtx(() => [
                                                      createTextVNode(toDisplayString(__props.laravel), 1)
                                                    ]),
                                                    _: 1
                                                  })
                                                ];
                                              }
                                            }),
                                            _: 1
                                          }, _parent7, _scopeId6));
                                          _push7(ssrRenderComponent(_component_CListGroupItem, null, {
                                            default: withCtx((_7, _push8, _parent8, _scopeId7) => {
                                              if (_push8) {
                                                _push8(`PHP `);
                                                _push8(ssrRenderComponent(_component_CBadge, { color: "primary" }, {
                                                  default: withCtx((_8, _push9, _parent9, _scopeId8) => {
                                                    if (_push9) {
                                                      _push9(`${ssrInterpolate(__props.php)}`);
                                                    } else {
                                                      return [
                                                        createTextVNode(toDisplayString(__props.php), 1)
                                                      ];
                                                    }
                                                  }),
                                                  _: 1
                                                }, _parent8, _scopeId7));
                                              } else {
                                                return [
                                                  createTextVNode("PHP "),
                                                  createVNode(_component_CBadge, { color: "primary" }, {
                                                    default: withCtx(() => [
                                                      createTextVNode(toDisplayString(__props.php), 1)
                                                    ]),
                                                    _: 1
                                                  })
                                                ];
                                              }
                                            }),
                                            _: 1
                                          }, _parent7, _scopeId6));
                                        } else {
                                          return [
                                            createVNode(_component_CListGroupItem, null, {
                                              default: withCtx(() => [
                                                createTextVNode("Laravel "),
                                                createVNode(_component_CBadge, { color: "primary" }, {
                                                  default: withCtx(() => [
                                                    createTextVNode(toDisplayString(__props.laravel), 1)
                                                  ]),
                                                  _: 1
                                                })
                                              ]),
                                              _: 1
                                            }),
                                            createVNode(_component_CListGroupItem, null, {
                                              default: withCtx(() => [
                                                createTextVNode("PHP "),
                                                createVNode(_component_CBadge, { color: "primary" }, {
                                                  default: withCtx(() => [
                                                    createTextVNode(toDisplayString(__props.php), 1)
                                                  ]),
                                                  _: 1
                                                })
                                              ]),
                                              _: 1
                                            })
                                          ];
                                        }
                                      }),
                                      _: 1
                                    }, _parent6, _scopeId5));
                                    _push6(ssrRenderComponent(_component_CAlert, {
                                      color: "primary",
                                      class: "mt-3 mb-0"
                                    }, {
                                      default: withCtx((_6, _push7, _parent7, _scopeId6) => {
                                        if (_push7) {
                                          _push7(` Brand primary token is live: this alert uses <code${_scopeId6}>#e53637</code>. `);
                                        } else {
                                          return [
                                            createTextVNode(" Brand primary token is live: this alert uses "),
                                            createVNode("code", null, "#e53637"),
                                            createTextVNode(". ")
                                          ];
                                        }
                                      }),
                                      _: 1
                                    }, _parent6, _scopeId5));
                                  } else {
                                    return [
                                      createVNode("p", { class: "text-body-secondary" }, " Laravel " + toDisplayString(__props.laravel) + " + Inertia + Vue 3 + CoreUI (Bootstrap 5). ", 1),
                                      createVNode(_component_CListGroup, { flush: "" }, {
                                        default: withCtx(() => [
                                          createVNode(_component_CListGroupItem, null, {
                                            default: withCtx(() => [
                                              createTextVNode("Laravel "),
                                              createVNode(_component_CBadge, { color: "primary" }, {
                                                default: withCtx(() => [
                                                  createTextVNode(toDisplayString(__props.laravel), 1)
                                                ]),
                                                _: 1
                                              })
                                            ]),
                                            _: 1
                                          }),
                                          createVNode(_component_CListGroupItem, null, {
                                            default: withCtx(() => [
                                              createTextVNode("PHP "),
                                              createVNode(_component_CBadge, { color: "primary" }, {
                                                default: withCtx(() => [
                                                  createTextVNode(toDisplayString(__props.php), 1)
                                                ]),
                                                _: 1
                                              })
                                            ]),
                                            _: 1
                                          })
                                        ]),
                                        _: 1
                                      }),
                                      createVNode(_component_CAlert, {
                                        color: "primary",
                                        class: "mt-3 mb-0"
                                      }, {
                                        default: withCtx(() => [
                                          createTextVNode(" Brand primary token is live: this alert uses "),
                                          createVNode("code", null, "#e53637"),
                                          createTextVNode(". ")
                                        ]),
                                        _: 1
                                      })
                                    ];
                                  }
                                }),
                                _: 1
                              }, _parent5, _scopeId4));
                            } else {
                              return [
                                createVNode(_component_CCardHeader, null, {
                                  default: withCtx(() => [
                                    createVNode("strong", null, "Shaniena v3 — Foundation")
                                  ]),
                                  _: 1
                                }),
                                createVNode(_component_CCardBody, null, {
                                  default: withCtx(() => [
                                    createVNode("p", { class: "text-body-secondary" }, " Laravel " + toDisplayString(__props.laravel) + " + Inertia + Vue 3 + CoreUI (Bootstrap 5). ", 1),
                                    createVNode(_component_CListGroup, { flush: "" }, {
                                      default: withCtx(() => [
                                        createVNode(_component_CListGroupItem, null, {
                                          default: withCtx(() => [
                                            createTextVNode("Laravel "),
                                            createVNode(_component_CBadge, { color: "primary" }, {
                                              default: withCtx(() => [
                                                createTextVNode(toDisplayString(__props.laravel), 1)
                                              ]),
                                              _: 1
                                            })
                                          ]),
                                          _: 1
                                        }),
                                        createVNode(_component_CListGroupItem, null, {
                                          default: withCtx(() => [
                                            createTextVNode("PHP "),
                                            createVNode(_component_CBadge, { color: "primary" }, {
                                              default: withCtx(() => [
                                                createTextVNode(toDisplayString(__props.php), 1)
                                              ]),
                                              _: 1
                                            })
                                          ]),
                                          _: 1
                                        })
                                      ]),
                                      _: 1
                                    }),
                                    createVNode(_component_CAlert, {
                                      color: "primary",
                                      class: "mt-3 mb-0"
                                    }, {
                                      default: withCtx(() => [
                                        createTextVNode(" Brand primary token is live: this alert uses "),
                                        createVNode("code", null, "#e53637"),
                                        createTextVNode(". ")
                                      ]),
                                      _: 1
                                    })
                                  ]),
                                  _: 1
                                })
                              ];
                            }
                          }),
                          _: 1
                        }, _parent4, _scopeId3));
                      } else {
                        return [
                          createVNode(_component_CCard, null, {
                            default: withCtx(() => [
                              createVNode(_component_CCardHeader, null, {
                                default: withCtx(() => [
                                  createVNode("strong", null, "Shaniena v3 — Foundation")
                                ]),
                                _: 1
                              }),
                              createVNode(_component_CCardBody, null, {
                                default: withCtx(() => [
                                  createVNode("p", { class: "text-body-secondary" }, " Laravel " + toDisplayString(__props.laravel) + " + Inertia + Vue 3 + CoreUI (Bootstrap 5). ", 1),
                                  createVNode(_component_CListGroup, { flush: "" }, {
                                    default: withCtx(() => [
                                      createVNode(_component_CListGroupItem, null, {
                                        default: withCtx(() => [
                                          createTextVNode("Laravel "),
                                          createVNode(_component_CBadge, { color: "primary" }, {
                                            default: withCtx(() => [
                                              createTextVNode(toDisplayString(__props.laravel), 1)
                                            ]),
                                            _: 1
                                          })
                                        ]),
                                        _: 1
                                      }),
                                      createVNode(_component_CListGroupItem, null, {
                                        default: withCtx(() => [
                                          createTextVNode("PHP "),
                                          createVNode(_component_CBadge, { color: "primary" }, {
                                            default: withCtx(() => [
                                              createTextVNode(toDisplayString(__props.php), 1)
                                            ]),
                                            _: 1
                                          })
                                        ]),
                                        _: 1
                                      })
                                    ]),
                                    _: 1
                                  }),
                                  createVNode(_component_CAlert, {
                                    color: "primary",
                                    class: "mt-3 mb-0"
                                  }, {
                                    default: withCtx(() => [
                                      createTextVNode(" Brand primary token is live: this alert uses "),
                                      createVNode("code", null, "#e53637"),
                                      createTextVNode(". ")
                                    ]),
                                    _: 1
                                  })
                                ]),
                                _: 1
                              })
                            ]),
                            _: 1
                          })
                        ];
                      }
                    }),
                    _: 1
                  }, _parent3, _scopeId2));
                } else {
                  return [
                    createVNode(_component_CCol, { md: 8 }, {
                      default: withCtx(() => [
                        createVNode(_component_CCard, null, {
                          default: withCtx(() => [
                            createVNode(_component_CCardHeader, null, {
                              default: withCtx(() => [
                                createVNode("strong", null, "Shaniena v3 — Foundation")
                              ]),
                              _: 1
                            }),
                            createVNode(_component_CCardBody, null, {
                              default: withCtx(() => [
                                createVNode("p", { class: "text-body-secondary" }, " Laravel " + toDisplayString(__props.laravel) + " + Inertia + Vue 3 + CoreUI (Bootstrap 5). ", 1),
                                createVNode(_component_CListGroup, { flush: "" }, {
                                  default: withCtx(() => [
                                    createVNode(_component_CListGroupItem, null, {
                                      default: withCtx(() => [
                                        createTextVNode("Laravel "),
                                        createVNode(_component_CBadge, { color: "primary" }, {
                                          default: withCtx(() => [
                                            createTextVNode(toDisplayString(__props.laravel), 1)
                                          ]),
                                          _: 1
                                        })
                                      ]),
                                      _: 1
                                    }),
                                    createVNode(_component_CListGroupItem, null, {
                                      default: withCtx(() => [
                                        createTextVNode("PHP "),
                                        createVNode(_component_CBadge, { color: "primary" }, {
                                          default: withCtx(() => [
                                            createTextVNode(toDisplayString(__props.php), 1)
                                          ]),
                                          _: 1
                                        })
                                      ]),
                                      _: 1
                                    })
                                  ]),
                                  _: 1
                                }),
                                createVNode(_component_CAlert, {
                                  color: "primary",
                                  class: "mt-3 mb-0"
                                }, {
                                  default: withCtx(() => [
                                    createTextVNode(" Brand primary token is live: this alert uses "),
                                    createVNode("code", null, "#e53637"),
                                    createTextVNode(". ")
                                  ]),
                                  _: 1
                                })
                              ]),
                              _: 1
                            })
                          ]),
                          _: 1
                        })
                      ]),
                      _: 1
                    })
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode(_component_CRow, { class: "justify-content-center" }, {
                default: withCtx(() => [
                  createVNode(_component_CCol, { md: 8 }, {
                    default: withCtx(() => [
                      createVNode(_component_CCard, null, {
                        default: withCtx(() => [
                          createVNode(_component_CCardHeader, null, {
                            default: withCtx(() => [
                              createVNode("strong", null, "Shaniena v3 — Foundation")
                            ]),
                            _: 1
                          }),
                          createVNode(_component_CCardBody, null, {
                            default: withCtx(() => [
                              createVNode("p", { class: "text-body-secondary" }, " Laravel " + toDisplayString(__props.laravel) + " + Inertia + Vue 3 + CoreUI (Bootstrap 5). ", 1),
                              createVNode(_component_CListGroup, { flush: "" }, {
                                default: withCtx(() => [
                                  createVNode(_component_CListGroupItem, null, {
                                    default: withCtx(() => [
                                      createTextVNode("Laravel "),
                                      createVNode(_component_CBadge, { color: "primary" }, {
                                        default: withCtx(() => [
                                          createTextVNode(toDisplayString(__props.laravel), 1)
                                        ]),
                                        _: 1
                                      })
                                    ]),
                                    _: 1
                                  }),
                                  createVNode(_component_CListGroupItem, null, {
                                    default: withCtx(() => [
                                      createTextVNode("PHP "),
                                      createVNode(_component_CBadge, { color: "primary" }, {
                                        default: withCtx(() => [
                                          createTextVNode(toDisplayString(__props.php), 1)
                                        ]),
                                        _: 1
                                      })
                                    ]),
                                    _: 1
                                  })
                                ]),
                                _: 1
                              }),
                              createVNode(_component_CAlert, {
                                color: "primary",
                                class: "mt-3 mb-0"
                              }, {
                                default: withCtx(() => [
                                  createTextVNode(" Brand primary token is live: this alert uses "),
                                  createVNode("code", null, "#e53637"),
                                  createTextVNode(". ")
                                ]),
                                _: 1
                              })
                            ]),
                            _: 1
                          })
                        ]),
                        _: 1
                      })
                    ]),
                    _: 1
                  })
                ]),
                _: 1
              })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<!--]-->`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Welcome.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const __vite_glob_0_0 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main
}, Symbol.toStringTag, { value: "Module" }));
function registerCoreUI(app) {
  for (const [name, component] of Object.entries(CoreUI)) {
    if (/^C[A-Z]/.test(name) && component && typeof component === "object") {
      app.component(name, component);
    }
  }
  app.component("CIcon", CIcon);
  return app;
}
const appName = process.env.VITE_APP_NAME || "Shaniena Empire";
createServer(
  (page) => createInertiaApp({
    page,
    render: renderToString,
    title: (title) => title ? `${title} — ${appName}` : appName,
    resolve: (name) => {
      const pages = /* @__PURE__ */ Object.assign({ "./Pages/Welcome.vue": __vite_glob_0_0 });
      return pages[`./Pages/${name}.vue`];
    },
    setup({ App, props, plugin }) {
      const app = createSSRApp({ render: () => h(App, props) }).use(plugin);
      return registerCoreUI(app);
    }
  })
);
