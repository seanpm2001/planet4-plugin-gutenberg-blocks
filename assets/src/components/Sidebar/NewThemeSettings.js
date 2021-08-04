import { SelectControl } from '@wordpress/components';
import { useState, useEffect } from 'react';
import { p4ServerThemes } from '../../theme/p4ServerThemes';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';

const keysAsLabel = obj => Object.keys(obj).map(k => ({ label: k, value: k }));

// Just a lightweight way to have a separate UI element in the sidebar so that it's clear which themes are legacy and
// which are new. This is likely just for internal usage to facilitate reviewing the refactored version, eventually
// editors will work with a single select.
const useServerThemes = () => {
  const [serverThemes, setServerThemes] = useState({});

  useEffect(() => {
    (async () => {
      const themes = await p4ServerThemes.fetchThemes();
      setServerThemes(themes);
    })();
  }, []);

  return serverThemes;
};

const getAllDefinedProps = () => Object.values(document.documentElement.style).filter(k => {
  return 'string' === typeof k && k.match(/^--/);
});

const useAppliedCssVariables = (serverThemes, currentTheme) => {
  const [initialVars] = useState(() => getAllDefinedProps(), []);

  const applyChangesToDom = () => {
    console.log('Applying ');
    const theme = serverThemes[currentTheme] || {};
    if (!theme) {
      return;
    }
    Object.entries(theme).forEach(([name, value]) => {
      // This will only work reliably if no other code is adding new custom properties to the root element after this
      // component is first rendered. This should be the case in the post editor.
      document.documentElement.style.setProperty(name, value);
    });

    const customProps = getAllDefinedProps();

    customProps.forEach(k => {
      if (!Object.keys(theme).includes(k) && !initialVars.includes(k)) {
        console.log('removing', k);
        document.documentElement.style.removeProperty(k);
      }
    });
  };
  useEffect(applyChangesToDom, [serverThemes, currentTheme]);
};

export const NewThemeSettings = ({ onChange, currentTheme }) => {
  const [selectedTheme, setSelectedTheme] = useState(currentTheme);
  const {editPost} = useDispatch('core/editor');
  const serverThemes = useServerThemes();

  const emitOnChange = () => {
    if (selectedTheme !== null) {
      const meta = { theme: selectedTheme };
      console.log(meta);
      onChange(selectedTheme);
      editPost({meta});
    }
  };
  useEffect(emitOnChange, [selectedTheme]);

  useAppliedCssVariables(serverThemes, currentTheme);

  if (Object.keys(serverThemes).length === 0) {
    return null;
  }

  return <div className="components-panel__body is-opened">
    <span>
      This dropdown contains themes that were created on this instance. It will be empty on production, as these won't
      have any themes created. Once we confirmed the created themes we add them to below dropdown.
    </span>
    <SelectControl
      label={ __('New Theme', 'planet4-blocks-backend') }
      title={ __('Only for reviewing themes, not intended to be 2 drop downs in the final version.', 'planet4-blocks-backend') }
      options={ [{ label: 'Legacy', value: '' }, ...keysAsLabel(serverThemes)] }
      onChange={ setSelectedTheme }
      value={ selectedTheme || '' }
    />
  </div>;
};