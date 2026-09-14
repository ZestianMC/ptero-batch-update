import * as React from 'react';
interface Props { isLoading?: boolean; size?: 'xsmall' | 'small' | 'large' | 'xlarge'; color?: 'green' | 'red' | 'primary' | 'grey'; isSecondary?: boolean }
declare const Button: React.FC<Props & Omit<JSX.IntrinsicElements['button'], 'ref' | 'size' | 'color'>>;
export default Button;
