import { useRef } from "react";
import { gsap } from "gsap";
import { useGSAP } from "@gsap/react";

gsap.registerPlugin(useGSAP);

/** One shared motion system for every route, including asynchronously loaded panels. */
export function useWorkspaceMotion(pathname: string) {
  const scope = useRef<HTMLDivElement>(null);
  useGSAP(
    () => {
      const root = scope.current;
      if (!root) return;
      const media = gsap.matchMedia();
      media.add("(prefers-reduced-motion: no-preference)", () => {
        const seen = new WeakSet<Element>();
        const animations = new Set<gsap.core.Tween>();
        const reveal = () => {
          const candidates = Array.from(
            root.querySelectorAll<HTMLElement>(
              ".page-heading, .stat-card, .panel, .info-banner, .policy-card, dialog[open]",
            ),
          );
          const fresh = candidates.filter((element) => {
            if (seen.has(element) || !element.getClientRects().length)
              return false;
            seen.add(element);
            return !element.parentElement?.closest(".panel, .policy-card");
          });
          if (!fresh.length) return;
          const animation = gsap.fromTo(
            fresh,
            { y: 14, opacity: 0 },
            {
              y: 0,
              opacity: 1,
              duration: 0.48,
              stagger: 0.055,
              ease: "power3.out",
              clearProps: "transform,opacity",
              onComplete: () => {
                animations.delete(animation);
              },
            },
          );
          animations.add(animation);
        };
        reveal();
        const observer = new MutationObserver(reveal);
        observer.observe(root, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ["open"],
        });
        return () => {
          observer.disconnect();
          animations.forEach((animation) => animation.revert());
          animations.clear();
        };
      });
      return () => media.revert();
    },
    { scope, dependencies: [pathname], revertOnUpdate: true },
  );
  return scope;
}
