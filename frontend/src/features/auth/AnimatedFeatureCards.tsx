import { useRef } from "react";
import { useGSAP } from "@gsap/react";
import { gsap } from "gsap";
import { BriefcaseBusiness, CalendarCheck2, UsersRound } from "lucide-react";

export function AnimatedFeatureCards() {
  const scope = useRef<HTMLDivElement>(null);
  useGSAP(
    () => {
      const root = scope.current!;
      const cards = Array.from(
        root.querySelectorAll<HTMLElement>(".galms-feature-tilt"),
      );
      const paths = Array.from(
        root.querySelectorAll<SVGPathElement>(".galms-link"),
      );
      let previous = "";
      const align = () => {
        const box = root.getBoundingClientRect();
        if (!box.width || !box.height) return;
        const points = cards.map((card) => {
          const rect = card.getBoundingClientRect();
          return {
            x: ((rect.left + rect.width / 2 - box.left) / box.width) * 500,
            y: ((rect.top + rect.height / 2 - box.top) / box.height) * 350,
          };
        });
        const key = points
          .map((point) => `${point.x.toFixed(1)},${point.y.toFixed(1)}`)
          .join(" ");
        if (key === previous) return;
        previous = key;
        points.forEach((a, i) => {
          const b = points[(i + 1) % points.length];
          const bend = i === 0 ? -50 : i === 1 ? 48 : -35;
          const d = `M${a.x} ${a.y} Q${(a.x + b.x) / 2 + bend} ${(a.y + b.y) / 2 + bend} ${b.x} ${b.y}`;
          paths[i * 2].setAttribute("d", d);
          paths[i * 2 + 1].setAttribute("d", d);
        });
      };
      align();
      const observer = new ResizeObserver(align);
      observer.observe(root);
      const media = gsap.matchMedia();
      media.add(
        "(min-width: 801px) and (prefers-reduced-motion: no-preference)",
        () => {
          gsap.ticker.add(align);
          return () => gsap.ticker.remove(align);
        },
      );
      return () => {
        observer.disconnect();
        media.revert();
      };
    },
    { scope },
  );
  return (
    <div
      ref={scope}
      className="galms-ecosystem"
      aria-label="Connected employee records, attendance management and leave administration"
    >
      <div className="galms-orbit" aria-hidden="true" />
      <svg
        className="galms-connections"
        viewBox="0 0 500 350"
        preserveAspectRatio="none"
        fill="none"
        aria-hidden="true"
      >
        {[0, 1, 2].map((index) => (
          <g key={index}>
            <path className="galms-link galms-track" />
            <path className="galms-link galms-pulse" pathLength="100" />
          </g>
        ))}
      </svg>
      <div className="galms-feature-position galms-feature-employees">
        <div className="galms-feature-float">
          <div className="galms-feature-tilt">
            <div className="galms-feature-card">
              <i className="galms-feature-shine" aria-hidden="true" />
              <UsersRound aria-hidden="true" />
              <span>
                Employee
                <br />
                Records
              </span>
              <small>PEOPLE, CONNECTED</small>
            </div>
          </div>
        </div>
      </div>
      <div className="galms-feature-position galms-feature-attendance">
        <div className="galms-feature-float">
          <div className="galms-feature-tilt">
            <div className="galms-feature-card">
              <i className="galms-feature-shine" aria-hidden="true" />
              <CalendarCheck2 aria-hidden="true" />
              <span>
                Attendance
                <br />
                Management
              </span>
              <small>EVERY DAY, ORGANIZED</small>
            </div>
          </div>
        </div>
      </div>
      <div className="galms-feature-position galms-feature-leave">
        <div className="galms-feature-float">
          <div className="galms-feature-tilt">
            <div className="galms-feature-card">
              <i className="galms-feature-shine" aria-hidden="true" />
              <BriefcaseBusiness aria-hidden="true" />
              <span>
                Leave
                <br />
                Administration
              </span>
              <small>TIME, WELL MANAGED</small>
            </div>
          </div>
        </div>
      </div>
      <span className="galms-ecosystem-caption">ONE CONNECTED WORKSPACE</span>
    </div>
  );
}
