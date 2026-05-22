import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Mail, Plus, Search, UserRound } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader } from '../../components/ui/card';
import { Drawer } from '../../components/ui/drawer';
import { Input, Select, Textarea } from '../../components/ui/input';
import { createSaasCustomer, listSaasCustomers, updateSaasCustomer, type SaasCustomer } from '../../services/api-v1-client';

type SaasCustomersProps = {
  accessToken: string;
};

export function SaasCustomers({ accessToken }: SaasCustomersProps) {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState<SaasCustomer | null>(null);
  const customersQuery = useQuery({
    queryKey: ['saas-customers', accessToken],
    queryFn: () => listSaasCustomers(accessToken),
    enabled: Boolean(accessToken),
  });
  const customers = customersQuery.data?.data ?? [];
  const filteredCustomers = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return customers;

    return customers.filter((customer) => [
      customer.first_name,
      customer.last_name,
      customer.email,
      customer.phone,
      customer.notes,
    ].join(' ').toLowerCase().includes(term));
  }, [customers, search]);
  const createCustomer = useMutation({
    mutationFn: (form: FormData) => createSaasCustomer(accessToken, {
      first_name: String(form.get('first_name') ?? ''),
      last_name: String(form.get('last_name') ?? ''),
      email: String(form.get('email') ?? '').toLowerCase(),
      phone: String(form.get('phone') ?? ''),
      notes: String(form.get('notes') ?? ''),
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['saas-customers', accessToken] });
      await queryClient.invalidateQueries({ queryKey: ['saas-dashboard', accessToken] });
      setDrawerOpen(false);
    },
  });
  const updateCustomer = useMutation({
    mutationFn: (form: FormData) => {
      if (!editingCustomer) throw new Error('Cliente no seleccionado.');

      return updateSaasCustomer(accessToken, {
        id: editingCustomer.id,
        first_name: String(form.get('first_name') ?? ''),
        last_name: String(form.get('last_name') ?? ''),
        email: String(form.get('email') ?? '').toLowerCase(),
        phone: String(form.get('phone') ?? ''),
        notes: String(form.get('notes') ?? ''),
        is_active: String(form.get('is_active') ?? '1') === '1',
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['saas-customers', accessToken] });
      await queryClient.invalidateQueries({ queryKey: ['saas-dashboard', accessToken] });
      closeDrawer();
    },
  });

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    if (editingCustomer) {
      updateCustomer.mutate(form);
      return;
    }

    createCustomer.mutate(form);
  };
  const openCreateDrawer = () => {
    setEditingCustomer(null);
    setDrawerOpen(true);
  };
  const openEditDrawer = (customer: SaasCustomer) => {
    setEditingCustomer(customer);
    setDrawerOpen(true);
  };
  const closeDrawer = () => {
    setDrawerOpen(false);
    setEditingCustomer(null);
    createCustomer.reset();
    updateCustomer.reset();
  };
  const saving = createCustomer.isPending || updateCustomer.isPending;
  const mutationError = createCustomer.error || updateCustomer.error;

  return (
    <>
      <Card>
        <CardHeader className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
          <div>
            <Badge tone="cyan"><UserRound size={14} className="mr-2" /> Clientes SaaS v1</Badge>
            <h2 className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-white">Base de clientes multiempresa.</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Datos reales desde PostgreSQL, aislados por empresa desde JWT. Esta pantalla reemplaza progresivamente el CRUD legacy.</p>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row">
            <label className="relative min-w-0 sm:w-80">
              <Search className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" size={17} />
              <Input className="pl-11" placeholder="Buscar cliente, correo o nota" value={search} onChange={(event) => setSearch(event.target.value)} />
            </label>
            <Button variant="primary" onClick={openCreateDrawer}><Plus size={16} className="mr-2" /> Nuevo cliente</Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex flex-wrap gap-2">
            <Badge tone="slate">{customers.length} clientes totales</Badge>
            <Badge tone="emerald">{filteredCustomers.length} visibles</Badge>
          </div>
          {customersQuery.isLoading ? <div className="rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-8 text-sm text-slate-400">Cargando clientes...</div> : null}
          {customersQuery.isError ? <div className="rounded-[1.5rem] border border-rose-300/20 bg-rose-400/10 p-4 text-sm text-rose-100">{customersQuery.error.message}</div> : null}
          {!customersQuery.isLoading && filteredCustomers.length === 0 ? (
            <div className="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.03] p-8 text-sm text-slate-400">
              {search ? 'No hay clientes que coincidan con la busqueda.' : 'Aun no hay clientes para este tenant.'}
            </div>
          ) : null}
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {filteredCustomers.map((customer) => (
              <article key={customer.id} className="rounded-[1.5rem] border border-white/10 bg-white/[0.045] p-4 transition hover:-translate-y-0.5 hover:bg-white/[0.07]">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex min-w-0 items-start gap-3">
                    <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white text-sm font-black text-zinc-950">
                      {customer.first_name.slice(0, 1)}{customer.last_name.slice(0, 1)}
                    </div>
                    <div className="min-w-0">
                      <h3 className="truncate font-semibold text-white">{customer.first_name} {customer.last_name}</h3>
                      <p className="mt-1 flex items-center gap-2 truncate text-sm text-slate-400"><Mail size={14} /> {customer.email}</p>
                    </div>
                  </div>
                  <Badge tone={customer.is_active ? 'emerald' : 'rose'}>{customer.is_active ? 'Activo' : 'Inactivo'}</Badge>
                </div>
                <div className="mt-4 rounded-2xl bg-black/20 p-3 text-sm text-slate-400">
                  <p className="truncate">Telefono: {customer.phone || '-'}</p>
                  <p className="mt-2 line-clamp-2">Notas: {customer.notes || 'Sin notas.'}</p>
                </div>
                <Button className="mt-4 w-full" onClick={() => openEditDrawer(customer)}>Editar cliente</Button>
              </article>
            ))}
          </div>
        </CardContent>
      </Card>

      <Drawer
        open={drawerOpen}
        title={editingCustomer ? 'Editar cliente' : 'Nuevo cliente'}
        description={editingCustomer ? 'Actualizar cliente dentro de la empresa autenticada.' : 'Crear cliente en PostgreSQL para la empresa autenticada.'}
        onClose={closeDrawer}
      >
        <form key={editingCustomer?.id ?? 'create'} className="space-y-4" onSubmit={submit}>
          <div className="grid gap-4 sm:grid-cols-2">
            <Input name="first_name" placeholder="Nombre" defaultValue={editingCustomer?.first_name ?? ''} required />
            <Input name="last_name" placeholder="Apellido" defaultValue={editingCustomer?.last_name ?? ''} required />
          </div>
          <Input name="email" type="email" placeholder="correo@dominio.cl" defaultValue={editingCustomer?.email ?? ''} required />
          <Input name="phone" placeholder="+56900000000" defaultValue={editingCustomer?.phone ?? ''} />
          {editingCustomer ? (
            <Select name="is_active" defaultValue={editingCustomer.is_active ? '1' : '0'}>
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </Select>
          ) : null}
          <Textarea name="notes" placeholder="Notas internas" defaultValue={editingCustomer?.notes ?? ''} />
          {mutationError ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{mutationError.message}</p> : null}
          <div className="flex gap-3">
            <Button variant="primary" type="submit" disabled={saving}>{saving ? 'Guardando...' : editingCustomer ? 'Guardar cambios' : 'Crear cliente'}</Button>
            <Button type="button" onClick={closeDrawer}>Cancelar</Button>
          </div>
        </form>
      </Drawer>
    </>
  );
}
