import { useRef } from "react";
import { gsap } from "gsap";
import { useGSAP } from "@gsap/react";

gsap.registerPlugin(useGSAP);

export function useLoginMotion(signedIn: boolean, submitting: boolean) {
  const scope = useRef<HTMLDivElement>(null);
  useGSAP(
    () => {
      const root = scope.current;
      if (!root) return;
      const media = gsap.matchMedia();
      media.add("(prefers-reduced-motion: no-preference)", () => {
        gsap
          .timeline({ defaults: { duration: 0.85, ease: "power3.out" } })
          .from(".galms-brand", { autoAlpha: 0, y: -12 })
          .from(".galms-division", { autoAlpha: 0, y: 12 }, 0.15)
          .fromTo(
            ".galms-headline-mask > span",
            { yPercent: 110, autoAlpha: 0 },
            { yPercent: 0, autoAlpha: 1, stagger: 0.16 },
            0.25,
          )
          .from(".galms-description", { autoAlpha: 0, y: 16 }, 0.55)
          .from(
            ".galms-feature-position",
            { autoAlpha: 0, scale: 0.9, y: 20, stagger: 0.14 },
            0.55,
          )
          .from(".galms-panel", { autoAlpha: 0, x: 24 }, 0.25)
          .from(
            ".galms-form-reveal",
            { autoAlpha: 0, y: 12, stagger: 0.07 },
            0.5,
          )
          .from(
            ".galms-headline-mask:last-child > span",
            { textShadow: "0 0 22px #b4ffb6aa", duration: 1.6 },
            0.9,
          );
        gsap.to(".galms-waves path", {
          x: 18,
          y: -4,
          duration: 9,
          stagger: 1.4,
          repeat: -1,
          yoyo: true,
          ease: "sine.inOut",
        });
      });
      media.add(
        "(min-width: 801px) and (prefers-reduced-motion: no-preference)",
        () => {
          gsap.utils
            .toArray<HTMLElement>(".galms-feature-float")
            .forEach((card, i) => {
              gsap.to(card, {
                y: i === 1 ? 6 : -7,
                rotation: i === 1 ? -1 : 1,
                duration: 4.2 + i * 0.6,
                delay: 1.5 + i * 0.4,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut",
              });
            });
          gsap.utils
            .toArray<SVGPathElement>(".galms-pulse")
            .forEach((path, i) => {
              gsap.fromTo(
                path,
                { strokeDashoffset: 2 },
                {
                  strokeDashoffset: -98,
                  duration: 7 + i * 2,
                  delay: i * 1.7,
                  repeat: -1,
                  ease: "none",
                },
              );
            });
          gsap.to(".galms-ambient", {
            xPercent: 8,
            yPercent: 8,
            scale: 1.08,
            duration: 12,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut",
          });
        },
      );
      media.add(
        "(min-width: 801px) and (hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)",
        () => {
          const intro = root.querySelector<HTMLElement>(".galms-intro")!;
          const spotlight =
            intro.querySelector<HTMLElement>(".galms-spotlight")!;
          const quick = (
            target: gsap.TweenTarget,
            property: string,
            duration = 0.85,
          ) => gsap.quickTo(target, property, { duration, ease: "power3.out" });
          const lightX = quick(spotlight, "x");
          const lightY = quick(spotlight, "y");
          const lightOpacity = quick(spotlight, "opacity");
          const layers = [
            {
              element: intro.querySelector(".galms-aura")!,
              strength: 3,
            },
            { element: intro.querySelector(".galms-grid")!, strength: 5 },
            { element: intro.querySelector(".galms-ecosystem")!, strength: 8 },
          ].map((layer) => ({
            ...layer,
            x: quick(layer.element, "x"),
            y: quick(layer.element, "y"),
          }));
          const tilts = Array.from(
            intro.querySelectorAll<HTMLElement>(".galms-feature-tilt"),
          ).map((card, i) => ({
            x: quick(card, "x"),
            y: quick(card, "y"),
            rx: quick(card, "rotationX"),
            ry: quick(card, "rotationY"),
            strength: 0.65 + i * 0.15,
          }));
          const move = (event: PointerEvent) => {
            if (event.pointerType !== "mouse") return;
            const rect = intro.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            const nx = Math.max(-1, Math.min(1, (x / rect.width) * 2 - 1));
            const ny = Math.max(-1, Math.min(1, (y / rect.height) * 2 - 1));
            lightX(x);
            lightY(y);
            lightOpacity(0.85);
            layers.forEach((layer) => {
              layer.x(nx * layer.strength);
              layer.y(ny * layer.strength);
            });
            tilts.forEach((card) => {
              card.x(nx * 3 * card.strength);
              card.y(ny * 2 * card.strength);
              card.rx(-ny * 4 * card.strength);
              card.ry(nx * 5 * card.strength);
            });
          };
          const reset = () => {
            lightX(intro.clientWidth * 0.45);
            lightY(intro.clientHeight * 0.6);
            lightOpacity(0.25);
            layers.forEach((layer) => {
              layer.x(0);
              layer.y(0);
            });
            tilts.forEach((card) => {
              card.x(0);
              card.y(0);
              card.rx(0);
              card.ry(0);
            });
          };
          gsap.set(spotlight, {
            x: intro.clientWidth * 0.45,
            y: intro.clientHeight * 0.6,
            opacity: 0.25,
          });
          intro.addEventListener("pointermove", move);
          intro.addEventListener("pointerleave", reset);
          return () => {
            intro.removeEventListener("pointermove", move);
            intro.removeEventListener("pointerleave", reset);
          };
        },
      );
      return () => media.revert();
    },
    { scope, dependencies: [signedIn], revertOnUpdate: true },
  );
  useGSAP(
    () => {
      if (!submitting || !scope.current) return;
      const media = gsap.matchMedia();
      media.add("(prefers-reduced-motion: no-preference)", () => {
        gsap.to(".galms-loading-icon", {
          rotation: 360,
          duration: 1.1,
          repeat: -1,
          ease: "none",
        });
        gsap.to(".galms-submit > span", {
          opacity: 0.65,
          duration: 0.7,
          repeat: -1,
          yoyo: true,
          ease: "sine.inOut",
        });
      });
      return () => media.revert();
    },
    { scope, dependencies: [submitting], revertOnUpdate: true },
  );
  return scope;
}
