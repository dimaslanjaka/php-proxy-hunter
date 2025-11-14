type DeepPartial<T> = {
  [K in keyof T]?: T[K] extends object ? DeepPartial<T[K]> : T[K];
};
type DeepRequired<T> = {
  [K in keyof T]-?: T[K] extends object
    ? T[K] extends (...args: any[]) => any
      ? NonNullable<T[K]> // keep functions as-is
      : DeepRequired<NonNullable<T[K]>>
    : NonNullable<T[K]>;
};
