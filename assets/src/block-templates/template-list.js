import * as sideImgTextCta from './side-image-with-text-and-cta';
import * as quickLinks from './quick-links';
import * as deepDive from './deep-dive';
import * as realityCheck from './reality-check';
import * as issues from './issues';
import * as pageHeader from './page-header';
import * as highlightedCta from './highlighted-cta';
import * as deepDiveTopic from './deep-dive-topic';
import * as homepage from './homepage';
import * as campaign from './campaign';
import * as takeAction from './take-action';
import * as action from './action';
import * as getInformed from './get-informed';
import * as highLevelTopic from './high-level-topic';

export default {
  // This section should be match against the 'Categories' Array for each block pattern
  // Planet 4 patterns
  planet4: [
    sideImgTextCta,
    quickLinks,
    deepDive,
    realityCheck,
    issues,
    highlightedCta,
  ],
  // Page Headers patterns
  pageHeaders: [
    pageHeader,
  ],
  // Layouts patterns
  layouts: [
    deepDiveTopic,
    homepage,
    campaign,
    takeAction,
    action,
    getInformed,
    highLevelTopic,
  ],
};
