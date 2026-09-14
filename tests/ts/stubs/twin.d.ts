declare module 'twin.macro' {
    const tw: (strings: TemplateStringsArray, ...rest: any[]) => any;
    export default tw;
}
declare namespace React {
    interface Attributes { css?: any }
}
declare namespace JSX {
    interface IntrinsicAttributes { css?: any }
}
